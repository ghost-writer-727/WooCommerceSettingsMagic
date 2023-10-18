<?php defined('ABSPATH') || exit;

if (!class_exists('WooCommerceSettingsHelper')) :
class WooCommerceSettingsHelper
{
    private $tab_name;
    private $slug;
    private $fields;

    // Cache for settings
    private $settings_cache = [];

    public static function addSettingsTab($tab_name, $slug, $fields)
    {
        $instance = new self($tab_name, $slug, $fields);
        return $instance->settings_cache[$slug] ?? false;
    }

    public function __construct($tab_name, $slug, $fields)
    {
        $this->tab_name = $tab_name;
        $this->slug = $slug;
        $this->fields = $this->normalize_fields($fields);

        $this->init();
    }

    public function get_settings(){
        return $this->settings_cache[$this->slug] ?? [];
    }

    public function get($field_id){
        return $this->settings_cache[$this->slug][$field_id] ?? false;
    }

    private function init()
    {
        // Register new settings tab
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_tab'], 50);

        // Add the settings to the tab
        add_action('woocommerce_settings_' . $this->slug, [$this, 'add_settings']);

        // Save the settings
        add_action('woocommerce_update_options_' . $this->slug, [$this, 'save_settings']);

        // Fetch and cache the settings from the DB
        $saved_settings = get_option($this->slug, []);
        $defaults = [];

        // Extract default values
        foreach ($this->fields as $key => $field) {
            if (isset($field['default'])) {
                $defaults[$key] = $field['default'];
            }
        }

        // Merge saved settings with defaults
        $this->settings_cache[$this->slug] = array_merge($defaults, $saved_settings);    
    }

    private function normalize_fields($fields){
        $current_section_id = '';
        $all_ids = [];
        foreach( $fields as &$field ){
            switch( $field['type'] ?? '' ){
                case 'title':
                    $field['title'] = $field['title'] ?? '';
                    $field['desc'] = $field['desc'] ?? '';
                    $field['id'] = $field['id'] ?? sanitize_title($field['title']);
                    $current_section_id = $field['id'];
                    break;
                case 'sectionend':
                    $field['id'] = $field['id'] ?? $current_section_id . '_end';
                    $current_section_id = '';
                    break;
                case 'text':
                case 'textarea':
                case 'number':
                case 'password':
                case 'date':
                case 'color':
                case 'select':
                case 'radio':
                case 'checkbox':
                case 'hidden':
                case 'multiselect':
                default:
                    // One of these parameters are requried.
                    $field['title'] = $field['title'] ?? $field['id'] ?? '';
                    $field['id'] = $field['id'] ?? sanitize_title($field['title']);

                    // Optional Parameters
                    $field['desc'] = $field['desc'] ?? $field['description'] ?? '';
                    $field['type'] = $field['type'] ?? 'text';
                    $field['default'] = $field['default'] ?? '';
                    $field['placeholder'] = $field['placeholder'] ?? '';
                    $field['class'] = $field['class'] ?? '';
                    $field['desc_tip'] = $field['desc_tip'] ?? false;
                    $field['custom_attributes'] = $field['custom_attributes'] ?? [];

                    // throw error if id is empty
                    if( ! $field['id'] ){
                        throw new Exception( __CLASS__ . ': $field needs an id or title');
                    }

                    // Throw error if id already exists
                    if( in_array($field['id'], $all_ids) ){
                        throw new Exception( __CLASS__ . ': $field[\'id\'] "' . $field['id'] . '" already exists');
                    }
                    $all_ids[] = $field['id'];

                    break;
            }
        }

        return $fields;
    }

    public function add_tab($tabs)
    {
        $tabs[$this->slug] = $this->tab_name;
        return $tabs;
    }

    public function add_settings()
    {
        woocommerce_admin_fields($this->fields);
    }

    public function save_settings()
    {
        woocommerce_update_options($this->fields);
    }
}
endif;

/*
Usage:

$fields = [
    'section_1' => [
        'title' => 'Section 1',
        'type' => 'title',
        'desc' => 'Section 1 description',
    ],
    'field_1' => [
        'id' => 'field_1',
        'title' => 'Field 1',
        'type' => 'text', // Optional
        'desc' => 'Field 1 description', // Optional
        'default' => 'Default value', // Optional
        'placeholder' => 'Placeholder value', // Optional
        'class' => 'field_1', // Optional
    ],
    'section_1_end' => [
        'type' => 'sectionend',
        'id' => 'section_1_end', // Optional
    ]
];
$settings = WooCommerceSettingsHelper::addSettingsTab('My Tab', 'my_tab', $fields);
*/
