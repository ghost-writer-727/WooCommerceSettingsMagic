<?php defined('ABSPATH') || exit;

if (!class_exists('WooCommerceSettingsMagic')) :
class WooCommerceSettingsMagic
{
    /**
     * User defined properties
     */
    private string $tab_name;
    private string $slug;

    /**
     * Internal properties
     */
    const SELECT2_CLASS = 'wcsm-apply-select2';
    private string $prefix;
    private array $settings_cache;
    private bool $select2_enabled;
    private string $current_section_id;
    private array $all_ids;
    private array $prepared_fields;
    private int $auto_index;

    /**
     * A magical static method for simultaneously adding a new settings tab 
     * and retrieving the settings array... PRESTO CHANGO!
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
        return $instance->settings_cache ?? false;
    }

    /**
     * OOP method for building a new settings tab
     * 
     * @param string $tab_name
     * @param string $slug
     * @param array $fields
     */
    public function __construct(string $tab_name, string $slug, array $fields){
        if( !preg_match('/^[a-z0-9-_]+$/', $slug) ){
            throw new Exception( __CLASS__ . ': $slug must be lowercase alphanumeric with dashes or underscores only');
        }
        $this->slug = $slug;
        $this->tab_name = $tab_name;
        $this->prefix = "wcsm-{$this->slug}-";
        $this->select2_enabled = false;
        $this->current_section_id = '';
        $this->all_ids = [];
        $this->prepared_fields = [];
        $this->auto_index = 0;

        $this->prepare_fields($fields);
        $this->prepare_settings_cache();
        $this->add_hooks();
    }

    /**
     * @return array The settings array
     */
    public function get_settings(){
        return $this->settings_cache;
    }

    /**
     * @param string $field_id The id of the field to retrieve
     * 
     * @return mixed The value of the field
     * @throws Exception if the field does not exist
     */
    public function get($field_id){
        if( ! isset($this->settings_cache[$field_id]) ){
            throw new Exception( __CLASS__ . ': $field_id "' . $field_id . '" does not exist');
        }

        return $this->settings_cache[$field_id];
    }

    private function add_hooks()
    {
        add_action('admin_enqueue_scripts', [$this, 'add_select2_js']);
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_tab'], 50);
        add_action('woocommerce_settings_' . $this->slug, [$this, 'add_settings']);
        add_action('woocommerce_update_options_' . $this->slug, [$this, 'save_settings']);
    }

    /**
     * Fetch the settings from the DB and cache them
     */
    private function prepare_settings_cache(){
        $non_settings_types = ['title','sectionend'];
        foreach( $this->prepared_fields as $field ){
            if( in_array($field['type'], $non_settings_types) ){
                continue;
            }
            $value = get_option($field['id'], $field['default'] ?? '');
            $key = $this->remove_prefix($field['id']);
            $this->settings_cache[$key] = $value;
        }
    }

    /**
     * Populates the prepared_fields property with the correct format
     * 
     * @param array $fields - compatible with woocommerce_admin_fields()
     */
    private function prepare_fields($fields){
        foreach( $fields as $field_key => $field ){
            // Everything goes within a section
            if( !$this->current_section_id && $field['type'] !== 'title' ){
                $this->build_section_start();
            }

            switch( $field['type'] ?? '' ){
                case 'title':
                    // Never nest sections
                    $this->maybe_build_section_end();
                    
                    // Build the new section
                    $id = $field['id'] ?? sanitize_title($field['title']) . '_section';
                    $this->prepared_fields[$field_key] = [
                        'type'  => $field['type'],
                        'title' => $field['title'] ?? '',
                        'desc'  => $field['desc'] ?? '',
                        'id'    => $id,
                    ];
                    $this->all_ids[] = $id;
                    $this->current_section_id = $id;
                    break;
                case 'sectionend':
                    // Close the previous section
                    $this->prepared_fields[$field_key] = [
                        'type'  => $field['type'],
                        'id'    => $field['id'] ?? $this->current_section_id . '_end',
                    ];
                    $this->current_section_id = '';
                    break;
                default:
                    if( ! $field['id'] ){
                        throw new Exception( __CLASS__ . ': $field[\'id\'] is required');                    
                    }

                    if( in_array($field['id'], $this->all_ids) ){
                        throw new Exception( __CLASS__ . ': $field[\'id\'] "' . $field['id'] . '" already exists');
                    }

                    if( $field['type'] == 'select' ){
                        if( $field['select2'] ?? false ){
                            $field = $this->process_field_add_select2($field);
                            $field = $this->process_field_assign_select_default($field, false);
                        } else {
                            $field = $this->process_field_assign_select_default($field);
                        }

                    } else if( $field['type'] == 'multiselect' ){
                        if( $field['select2'] ?? false ){
                            $field = $this->process_field_add_select2($field);
                        }
                        $field = $this->process_field_assign_select_default($field, false);

                    } else if( $field['type'] == 'radio' ){
                        $field = $this->process_field_assign_select_default($field);

                    } else if( $field['type'] == 'checkbox'){
                        $field = $this->process_field_assign_checkbox_default($field);

                    }

                    $this->prepared_fields[$field_key] = [
                        // Must have id
                        'id' => $field['id'],
                        
                        // Optional
                        'title'             => $field['title'] ?? $field['id'],
                        'type'              => $field['type'] ?? 'text',
                        'desc'              => $field['desc'] ?? '',
                        'default'           => $field['default'] ?? '',
                        'placeholder'       => $field['placeholder'] ?? '',
                        'class'             => $field['class'] ?? '',
                        'desc_tip'          => $field['desc_tip'] ?? false,
                        'custom_attributes' => $field['custom_attributes'] ?? [],
                    ];

                    // Remove processed parameters
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

                    // Include outlying parameters
                    foreach( $field as $param => $value ){
                        $this->prepared_fields[$field_key][$param] = $value;
                    }

                    break;
            }

            $this->all_ids[] = $this->prepared_fields[$field_key]['id'];
        }

        $this->maybe_build_section_end();

        $this->prefix_ids();
    }

    /**
     * Prepares a new section/title field
     */
    private function build_section_start(){
        $id = $this->get_unique_title_id();

        $this->prepared_fields[$id] = [
            'type' => 'title',
            'title' => 'Settings',
            'desc' => '',
            'id' => $id,
        ];
        $this->all_ids[] = $id;
        $this->current_section_id = $id;
    }

    /**
     * @return string A unique id for a title field
     */
    private function get_unique_title_id(){
        // Recursively increment up until it finds a unique id
        $id = "section_{$this->slug}-{$this->auto_index}";
        while( in_array( $id, $this->all_ids ) ){
            $this->auto_index++;
            $id = "section_{$this->slug}-{$this->auto_index}";
        }
        return $id;
    }

    /**
     * Prepares a sectionend field, if necessary
     */
    private function maybe_build_section_end(){
        if( $this->current_section_id ){
            $end_id = $this->current_section_id . '_end';
            $this->prepared_fields[$end_id] = [
                'type' => 'sectionend',
                'id' => $end_id,
            ];
            $this->current_section_id = '';
        }
    }

    /**
     * @param array $field
     * 
     * @return array The processed field
     */
    private function process_field_add_select2( $field ){
        // Flag select2 js to be enqueued
        $this->select2_enabled = true;
        
        // Apply class to be read in by our select2 function
        $field['class'] = ($field['class'] ?? '') . ' ' . self::SELECT2_CLASS;
        
        // Add flags for custom attributes to be read in with our select2 logic
        $field['custom_attributes'] = $field['custom_attributes'] ?? [];
        $field['custom_attributes']['data-placeholder'] = $field['placeholder'] ?? 'Select an option...';
        $field['custom_attributes']['data-allowClear'] = $field['allowClear'] ?? true;
        
        // Remove the non-standard parameters to prevent future conflicts
        unset( $field['select2'], $field['allowClear'] );

        return $field;
    }

    /**
     * @param array $field
     * @param bool $autoselect - whether or not to autoselect the first option
     * 
     * @return array The processed field
     */
    private function process_field_assign_select_default($field, $autoselect = true){
        // Check if default is already assigned & is a valid option.
        if( 
            isset( $field['default'], $field['options'] ) 
            && is_array( $field['options'] )
            && in_array($field['default'], array_keys($field['options']))
        ){
            return $this->maybe_add_empty_option($field);
        }

        // Define maybe missing parameters
        $field['default'] = $field['default'] ?? '';
        $field['options'] = $field['options'] ?? [];

        // Set the default to the first option
        if( $autoselect ){
            if( count($field['options']) ){
                $field['default'] = array_key_first($field['options']);
            } else {
                // Returns null if no options are populated
                $field['default'] = null;
            }
        } else {
            $field = $this->maybe_add_empty_option($field);
            $field['default'] = '';
        }

        return $field;
    }

    /**
     * Checks if the field type is valid for an empty option and adds one if necessary
     * 
     * @param array $field
     * 
     * @return array The potentially updated field
     */
    private function maybe_add_empty_option($field){
        if( 
            $field['type'] == 'select' 
            && strpos($field['class'], self::SELECT2_CLASS) !== false 
        ){
            return $this->add_empty_option($field);
        }
        return $field;
    }

    /**
     * Adds an empty option to the beginning of the options array, if not present
     * 
     * @param array $field
     * 
     * @return array The updated field
     */
    private function add_empty_option($field){
        if( ! in_array( '', array_keys($field['options']) ) ){
            $field['options'] = array_merge(['' => ''], $field['options']);
        }
        return $field;
    }

    /**
     * Conform the default value to a valid checkbox value
     * 
     * @param array $field
     * 
     * @return array The processed field
     */
    private function process_field_assign_checkbox_default($field){
        $yes = 'yes';
        if( 
            isset($field['default']) &&  
            ( $field['default'] === $yes || $field['default'] === true )
        ){
            $field['default'] = $yes;
        } else {
            $field['default'] = '';
        }

        return $field;
    }

    /**
     * Preppend the slug to the field ids to prevent conflicts
     */
    private function prefix_ids(){
        foreach( $this->prepared_fields as &$field ){
            $field['id'] = $this->prefix . $field['id'];
        }
    }

    /**
     * Remove the preppended slug from the field ids
     * 
     * @param string $id
     * 
     * @return string The processed id
     */
    private function remove_prefix($id){
        if( substr($id, 0, strlen($this->prefix)) === $this->prefix ){
            return substr($id, strlen($this->prefix));
        }
        return $id;
    }

    /**
     * Callback for adding a new WooCommerce settings tab
     */
    public function add_tab($tabs){
        $tabs[$this->slug] = $this->tab_name;
        return $tabs;
    }
    
    /**
     * Callback for adding fields to the new WooCommerce settings tab
     */
    public function add_settings(){
        woocommerce_admin_fields($this->prepared_fields);
    }
    
    /**
     * Callback for saving the new WooCommerce settings tab
     */
    public function save_settings(){
        woocommerce_update_options($this->prepared_fields);
    }

    /**
     * Callback for enqueuing Select2 JS when needed
     */
    public function add_select2_js(){
        $page = sanitize_text_field($_GET['page'] ?? '');
        $tab = sanitize_text_field($_GET['tab'] ?? '');

        // Skip this if our Select2 is not required
        if( 
            !$this->select2_enabled
            || $page !== 'wc-settings' 
            || $tab !== $this->slug 
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
}
endif;
