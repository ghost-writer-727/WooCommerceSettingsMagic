<?php defined('ABSPATH') || exit;

if (!class_exists('WooCommerceSettingsMagic')) :
class WooCommerceSettingsMagic
{
    /**
     * @var string
     * The name of the tab
     */
    private $tab_name;

    /**
     * @var string
     * 
     * The slug of the tab and the option name in the database
     */
    private $slug;

    /**
     * @var array
     * 
     * Each setting field must have at least an id or a title
     * All other parameters required by woocommerce_admin_fields() will be generated automatically if missing
     */
    private $fields;

    /**
     * @var array
     * 
     * An associative array of the settings values
     */
    private $settings_cache = [];

    /**
     * @var bool
     * 
     * Whether or not to enable Select2 support
     */
    private $select2_enabled;

    /**
     * Quick static method for adding a new settings tab and retrieving the settings array
     * 
     * @param string $tab_name
     * @param string $slug
     * @param array $fields
     * 
     * @return array|false The settings array or false if the tab already exists
     * @throws Exception if $fields is not formatted correctly
     */
    public static function presto($tab_name, $slug, $fields)
    {
        $instance = new self($tab_name, $slug, $fields);
        return $instance->settings_cache[$slug] ?? false;
    }

    /**
     * Traditional method for building a new settings tab
     * 
     * @param string $tab_name
     * @param string $slug
     * @param array $fields
     */
    public function __construct($tab_name, $slug, $fields)
    {
        $this->tab_name = $tab_name;
        $this->slug = $slug;
        $this->fields = $this->normalize_fields($fields);

        $this->init();
    }

    /**
     * For use with traditional method
     * 
     * @return array The settings array
     */
    public function get_settings(){
        return $this->settings_cache[$this->slug] ?? [];
    }

    /**
     * For use with traditional method
     * 
     * @param string $field_id The id of the field to retrieve
     * 
     * @return mixed The value of the field
     * @throws Exception if the field does not exist
     */
    public function get($field_id){
        if( ! isset($this->settings_cache[$this->slug][$field_id]) ){
            throw new Exception( __CLASS__ . ': $field_id "' . $field_id . '" does not exist');
        }

        return $this->settings_cache[$this->slug][$field_id];
    }

    private function init()
    {
        // Add Select2 support
        add_action('admin_enqueue_scripts', [$this, 'add_select2_support']);

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
        // Stores which section we are currently in so we can add sectionends automatically
        $current_section_id = '';

        // Stores all ids to check for duplicates
        $all_ids = [];

        // Rebuild all fields with normalized parameters so we can insert missing sectionends automatically
        $normalized_fields = [];
        foreach( $fields as $key => $field ){
            // If we are not in a section and this field isn't a title, add a title automatically
            if( !$current_section_id && $field['type'] !== 'title' ){
                // Must be a random ID in case we have multiple instances with this scenario
                $index = array_search($key, array_keys($fields)) . '_' . rand(100000, 999999);
                $auto_title_key = 'settings_title_auto_generated_' . $index;
                $normalized_fields[$auto_title_key] = [
                    'type' => 'title',
                    'title' => 'Settings',
                    'desc' => '',
                    'id' => $auto_title_key,
                ];
                $current_section_id = $normalized_fields[$auto_title_key]['id'];
            }

            // Convert non-associative array keys to 
            if( is_int($key) ){
                $key = $field['id'] ?? sanitize_title($field['title']) . '_' . rand(100000, 999999);
            }

            switch( $field['type'] ?? '' ){
                case 'title':
                    if( $current_section_id ){
                        // If missing previous sectionend, add it automatically before the new section
                        $end_id = $current_section_id . '_end';
                        $normalized_fields[$end_id] = [
                            'type' => 'sectionend',
                            'id' => $end_id,
                        ];
                    }

                    $normalized_fields[$key] = [
                        'type'  => $field['type'],
                        'title' => $field['title'] ?? '',
                        'desc'  => $field['desc'] ?? '',
                        'id'    => $field['id'] ?? sanitize_title($field['title']) . '_section',
                    ];
                    $current_section_id = $normalized_fields[$key]['id'];
                    break;
                case 'sectionend':
                    $normalized_fields[$key] = [
                        'type'  => $field['type'],
                        'id'    => $field['id'] ?? $current_section_id . '_end',
                    ];
                    $current_section_id = '';
                    break;
                default:
                // Here we must set defaults for various field types to match what appears when first loaded up, because defaults are used when generating the $settings_cache array. This way, what is visible will match the actual value returned when getting settings via this class, even prior to the first click of the "Save Changes" button.
                    if( $field['type'] == 'select' ){
                        $field['default'] = $field['default'] ?? '';
                        $field['options'] = $field['options'] ?? [];

                        if( $field['select2'] ?? false ){
                            // Apply select2 to this field
                            $this->select2_enabled = true;
                            $field['class'] = $field['class'] ?? '';
                            $field['class'] .= ' wcsm-apply-select2';
                            unset( $field['select2'] );

                            // Ensure we have custom_attributes created
                            $field['custom_attributes'] = $field['custom_attributes'] ?? [];

                            // Add a placeholder that can be read in with select2 initialization
                            $field['custom_attributes']['data-placeholder'] = $field['placeholder'] ?? 'Select an option...';

                            // Set the default default to ''
                            if( ! in_array($field['default'], array_keys($field['options'])) ){
                                $field['default'] = '';
                            }
                            
                            if( 
                                !$field['default']
                                && ! in_array( '', array_keys($field['options'])) 
                            ){
                                // Add an empty option to the beginning of the options array
                                $field['options'] = array_merge(['' => ''], $field['options']);
                            }
                        } else {
                            // If this isn't a select2, then default the selected option to the first one, since it will appear as selected when viewing the settings page, even if it hasn't been saved.
                            if( ! in_array($field['default'], array_keys($field['options'])) ){
                                // if there are any options
                                if( count($field['options']) ){
                                    // Set the default to the first option
                                    $field['default'] = array_key_first($field['options']);
                                }
                            }
                        }
                    } else if( $field['type'] == 'multiselect' ){
                        // Multiselect default needs to be an array
                        $field['default'] = (array) ( $field['default'] ?? [] );

                        // Add Select2 support
                        if( $field['select2'] ?? false ){
                            // Apply select2 to this field
                            $this->select2_enabled = true;
                            $field['class'] = $field['class'] ?? '';
                            $field['class'] .= ' wcsm-apply-select2';
                            unset( $field['select2'] );

                            // Ensure we have custom_attributes created
                            $field['custom_attributes'] = $field['custom_attributes'] ?? [];

                            // Add a placeholder that can be read in with select2 initialization
                            $field['custom_attributes']['data-placeholder'] = $field['placeholder'] ?? 'Select an option...';

                            // Add a default value that can be read in with select2 initialization
                            $field['custom_attributes']['data-default'] = $field['default'];
                        }
                    } else if( $field['type'] == 'radio' ){
                        // Radio default needs to be a string
                        $field['default'] = (string) ( $field['default'] ?? '' );
                        if( !in_array($field['default'], array_keys($field['options'])) ){
                            // if there are any options
                            if( count($field['options']) ){
                                // Set the default to the first option
                                $field['default'] = array_key_first($field['options']);
                            }
                        }
                    } else if( $field['type'] == 'checkbox'){
                        // Checkbox default needs to be a string
                        $field['default'] = (string) ( $field['default'] ?? '' );
                        if( !in_array($field['default'], ['yes', 'no']) ){
                            $field['default'] = 'no';
                        }
                    }

                    $normalized_fields[$key] = [
                        // One of these 2 parameters are requried to build the other if missing.
                        'title' => $field['title'] ?? $field['id'] ?? '',
                        'id'    => $field['id'] ?? sanitize_title($field['title']),

                        // Optional Parameters
                        'type'  => $field['type'] ?? 'text',
                        'desc'  => $field['desc'] ?? '',
                        'default' => $field['default'] ?? '',
                        'placeholder' => $field['placeholder'] ?? '',
                        'class' => $field['class'] ?? '',
                        'desc_tip' => $field['desc_tip'] ?? false,
                        'custom_attributes' => $field['custom_attributes'] ?? [],
                    ];

                    // Look for any other custom parameters and add them to the normalized array
                    unset( 
                        $field['title'], 
                        $field['id'], 
                        $field['type'], 
                        $field['desc'], 
                        $field['default'], 
                        $field['placeholder'], 
                        $field['class'], 
                        $field['desc_tip'], 
                        $field['custom_attributes']
                    );
                    foreach( $field as $param => $value ){
                        $normalized_fields[$key][$param] = $value;
                    }

                    // throw error if $field id && title were empty
                    if( ! $normalized_fields[$key]['id'] ){
                        throw new Exception( __CLASS__ . ': $field needs an id or title');
                    }

                    // Throw error if id already exists
                    if( in_array($normalized_fields[$key]['id'], $all_ids) ){
                        throw new Exception( __CLASS__ . ': $field[\'id\'] "' . $normalized_fields[$key]['id'] . '" already exists');
                    }
                    
                    break;
            }
            // Store ids for duplicate checking
            $all_ids[] = $normalized_fields[$key]['id'];
        }

        // If missing the final sectionend, add it automatically
        if( $current_section_id ){
            $end_id = $current_section_id . '_end';
            $normalized_fields[$end_id] = [
                'type' => 'sectionend',
                'id' => $end_id,
            ];
        }

        error_log( print_r( $normalized_fields, true ));

        return $normalized_fields;
    }

    public function add_select2_support(){
        if( 
            // Only do this if Select2 is requested
            !$this->select2_enabled

            // Only do this if we are on the correct page
            || !isset($_GET['page']) 
            || $_GET['page'] !== 'wc-settings' 
            || !isset($_GET['tab']) 
            || $_GET['tab'] !== $this->slug 
        ){
            return;
        }

        wp_enqueue_style('select2');
        wp_enqueue_script(
            $this->slug, 
            plugin_dir_url(__FILE__) . 'js/select2Support.js', 
            [
                'jquery',
                'selectWoo'
            ], 
            filemtime(plugin_dir_path(__FILE__) . 'js/select2Support.js'),
            true
        );
    }

    public function add_tab($tabs)
    {
        $tabs[$this->slug] = $this->tab_name;
        return $tabs;
    }

    public function add_settings()
    {
        error_log( print_r( $this->fields, true ));
        woocommerce_admin_fields($this->fields);
    }

    public function save_settings()
    {
        woocommerce_update_options($this->fields);
    }
}
endif;

