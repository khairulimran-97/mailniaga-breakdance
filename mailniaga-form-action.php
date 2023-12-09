<?php
/**
 * Plugin Name: Breakdance Forms MailNiaga v2
 * Description: Custom addon for Breakdance Forms which adds new subscriber to MailNiaga v2 after form submission.
 * Plugin URI:  https://lamanweb.my/
 * Version:     1.0.0
 * Author:      Laman Web
 * Author URI:  https://lamanweb.my/
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'enqueue.php';
require_once plugin_dir_path( __FILE__ ) . 'setting-page.php';
require_once plugin_dir_path( __FILE__ ) . 'api-call.php';

// Include Breakdance if available
if (!function_exists('\Breakdance\Forms\Actions\registerAction') || !class_exists('\Breakdance\Forms\Actions\Action')) {
    add_action('admin_notices', 'custom_form_action_missing_breakdance_notice');
    return;
}

function custom_form_action_missing_breakdance_notice() {
    ?>
    <div class="error">
        <p><?php _e('Custom Form Action requires Breakdance plugin to be installed and active. Please install and activate Breakdance.', 'custom-form-action'); ?></p>
    </div>
    <?php
}

// Include Breakdance Elements functions
use function Breakdance\Elements\control;
use function Breakdance\Elements\controlSection;
use function Breakdance\Elements\repeaterControl;

//use Requests_Utility_CaseInsensitiveDictionary;

class CustomFormAction extends \Breakdance\Forms\Actions\Action {

    public static function name() {
        return 'MailNiaga v2';
    }

    public static function slug() {
        return 'mailniaga-form';
    }
	
	public function controls()
    {
        // Call the function to fetch Mailniaga lists
        fetch_mailniaga_lists();

        $mailniaga_lists = get_option( 'mailniaga_lists', [] );

        return [
            control('mailniaga_list_id', 'Mailniaga List UID', [
                'type' => 'dropdown',
                'layout' => 'vertical',
                'placeholder' => 'No selected',
                'items' => array_map(
                    function( $uid, $name ) {
                        return ['text' => $name, 'value' => $uid];
                    },
                    array_keys( $mailniaga_lists ),
                    array_values( $mailniaga_lists )
                ),
            ]),
            control('mailniaganame', 'Name Field ID', [
                'type' => 'text',
                'layout' => 'vertical',
                'variableOptions' => [
                    'enabled' => true,
                    'populate' => [
                        'path' => 'content.form.fields',
                        'text' => 'label',
                        'value' => 'advanced.id',
                    ]
                ]
            ]),
			control('email', 'Email Field ID', [
						'type' => 'text',
						'layout' => 'vertical',
						'variableOptions' => [
							'enabled' => true,
							'populate' => [
								'path' => 'content.form.fields',
								'text' => 'label',
								'value' => 'advanced.id',
							'condition' => [
                                    'path' => 'type',
                                    'operand' => 'is one of',
                                    'value' => ['email']
                            ]
						]
						]
					]),
            repeaterControl('mailniaga_custom_field', 'Custom Field',
                [
                    control('name', 'Custom Field Name', [
                        'type' => 'text',
                        'layout' => 'vertical',
						'placeholder' => 'Field Name Tag From MailNiaga',
                    ]),
                    control('value', 'Custom Field Value', [
                        'type' => 'text',
                        'layout' => 'vertical',
                        'variableOptions' => [
                            'enabled' => true,
                            'populate' => [
                                'path' => 'content.form.fields',
                                'text' => 'label',
                                'value' => 'advanced.id'
                            ]
                        ]
                    ]),
                ],
                [
                    'repeaterOptions' => [
                        'titleTemplate' => '{name}',
                        'defaultTitle' => 'Custom Field',
                        'buttonName' => 'Add Custom Field'
                    ]
                ]
            ),
        ];
    }


    public function run($form, $settings, $extra)
	{
        $url = 'https://manage.mailniaga.com/api/v1/subscribers';
        $apiToken = get_option( 'mailniaga_api_key' );
        $listUid = $settings['actions']['mailniaga-form']['mailniaga_list_id'];
        
        $mailniaganame = $settings['actions']['mailniaga-form']['mailniaganame'];
        $email = $settings['actions']['mailniaga-form']['email'];
        
        // Remove curly braces from $mailniaganame
        $mailniaganame = str_replace(['{', '}'], '', $mailniaganame);
        $email = str_replace(['{', '}'], '', $email);
        
        $body = [
            'api_token' => $apiToken,
            'list_uid' => $listUid,
            'EMAIL' => $extra['fields'][$email],
            'FIRST_NAME' => $extra['fields'][$mailniaganame],
        ];

    $fieldMap = $settings['actions']['mailniaga-form']['mailniaga_custom_field'];
    $arrayVariables = $this->mapArrayFieldsToVariables($extra['fields']);
    $stringVariables = $this->mapStringFieldsToVariables($extra['fields']);
    $fieldVariableKeys = array_keys($stringVariables);
    $fieldVariableValues = array_values($stringVariables);

    foreach ($fieldMap as $field) {
        $body[$field['name']] = str_replace($fieldVariableKeys, $fieldVariableValues, $field['value']);
        if (array_key_exists(trim($field['value']), $arrayVariables)) {
            $body[$field['name']] = $arrayVariables[trim($field['value'])];
        }
    }

    $headers = [
        'accept' => 'application/json',
    ];

    $response = wp_remote_post($url, [
        'body' => $body,
        'headers' => $headers,
    ]);

    $responseHeaders = wp_remote_retrieve_headers($response);

    if ($responseHeaders instanceof Requests_Utility_CaseInsensitiveDictionary) {
        $responseHeaders = $responseHeaders->getAll();
    }

    $this->addContext('Request Body', $body);

    if (!empty($response)) {
        $this->addContext('Response Headers', $responseHeaders);
    }

    if ($response instanceof \WP_Error) {
        $this->addContext('Response Body', [
            'message' => $response->get_error_message(),
            'data' => $response->get_error_data(),
        ]);

        return [
            'type' => 'error',
            'message' => $response->get_error_message(),
        ];
    }

    $this->addContext('Response Body', \Breakdance\Forms\jsonDecodeIfValidJson($response));

    return ['type' => 'success'];
}

	
	/**
     * Returns fields with field names in variable syntax
     *  for string replacing i.e "{key}" => $value
     *
     * @param FormUserSubmittedContents $fields
     * @return array<string, array<array-key, mixed>|string>
     */
    private function mapStringFieldsToVariables($fields) {
        $fieldVariables = [];
        foreach ($fields as $fieldKey => $fieldValue) {
            $fieldVariableKey = sprintf('{%s}', $fieldKey);
            if (is_string($fieldValue)) {
                $fieldVariables[$fieldVariableKey] = $fieldValue;
            }
        }

        return $fieldVariables;
    }


    /**
     * Returns array fields with field names as keys
     *
     * @param FormUserSubmittedContents $fields
     * @return array<string, array<array-key, mixed>|string>
     */
    private function mapArrayFieldsToVariables($fields) {
        $arrayVariables = [];
        foreach ($fields as $fieldKey => $fieldValue) {
            if (is_array($fieldValue)) {
                $fieldVariableKey = sprintf('{%s}', $fieldKey);
                $arrayVariables[$fieldVariableKey] = $fieldValue;
            }
        }

        return $arrayVariables;
    }

}

// Register the action
add_action('init', function() {
    \Breakdance\Forms\Actions\registerAction(new CustomFormAction());
});

add_filter( 'plugin_action_links', 'add_action_plugin', 10, 5 );

function add_action_plugin( $actions, $plugin_file )
{
   static $plugin;

   if (!isset($plugin))
		$plugin = plugin_basename(__FILE__);
   if ($plugin == $plugin_file) {

      $settings = array('settings' => '<a href="admin.php?page=mailniaga-settings">' . __('Settings', 'General') . '</a>');
      $site_link = array('support' => '<a href="https://lamanweb.my/hubungi/" target="_blank">Support</a>');

      $actions = array_merge($site_link, $actions);
      $actions = array_merge($settings, $actions);
      
   }

   return $actions;
}
