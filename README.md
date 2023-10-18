# WooCommerceSettingsMagic Class

## Overview

This PHP class, `WooCommerceSettingsMagic`, is designed to streamline the process of adding custom settings tabs to WooCommerce. It allows for quick initialization, the addition of fields, and data retrieval. 

## Usage

### Quick Setup (Static Method)

The quickest way to add a settings tab is to use the `presto` static method. This will return the settings array for that specific tab.

```php
$settings = WooCommerceSettingsMagic::presto("My Tab", "my_slug", $fields_array);
```

### Traditional Setup (Object-Oriented Method)

1. Instantiate the class:

```php
$instance = new WooCommerceSettingsMagic("My Tab", "my_slug", $fields_array);
```

2. Get the settings:

```php
$settings = $instance->get_settings();
```

## Parameters

- **$tab_name**: The display name of the tab.
- **$slug**: The slug for the tab and the name of the database option.
- **$fields_array**: An array defining the fields to display in the tab.

## Fields Array Parameter Details

The `$fields_array` is an array of associative arrays, each representing a WooCommerce setting field or section. Below are the field options you can use:

### Section Field Options

#### Title Type

The `title` type denotes the start of a section. If missing, it will be autogenerated. (Optional)

- **`type`**: Must be set to `'title'`.
- **`title`**: The section title.
- **`id`**: Identifier for the field. (Optional if `title` is provided)
- **`desc`**: Description text for the field. (Optional)

#### SectionEnd Type

The `sectionend` type denotes the end of a section. If missing, it will be autogenerated. (Optional)

- **`type`**: Must be set to `'sectionend'`.
- **`id`**: Identifier for the field. Defaults to previous title field id + "_end". (Optional)

### Common Field Options

These options can generally be applied to all non-section field types:

- **`id`**: Identifier for the field. (Optional if `title` is provided)
- **`title`**: Display title of the field. (Optional if `id` is provided)
- **`desc`**: Description text for the field. (Optional)
- **`type`**: Field type (e.g., 'text', 'number', etc.). Defaults to 'text' if missing. (Optional)
- **`default`**: Default value for the field. (Optional)
- **`placeholder`**: Placeholder text for the field. (Optional)
- **`class`**: Custom CSS class for the field. (Optional)
- **`desc_tip`**: Boolean to show description as tooltip. (Optional)
- **`custom_attributes`**: Any additional attributes in associative array format. (Optional)

### Non-Section Field Types

#### Text, Textarea, Number, Password, Date, Color

For these types, all common field options can be used.

#### Select, Radio

- **`options`**: An associative array of available options. (Optional)

#### Checkbox

- **`checkboxgroup`**: Values can be `'start'`, `'middle'` or `'end'`. Starting checkbox `'title'` will serve as the group title. Grouped checkboxes `'desc'` will serve as the checkbox text. (Optional)

#### Multiselect

- **`options`**: An associative array of available options. (Optional)
- **`select_buttons`**: Boolean, to use buttons instead of dropdown. (Optional)

## Methods

### `presto($tab_name, $slug, $fields)`

Static method for adding a new settings tab.

- **Return**: The settings array or `false` if the tab already exists.

### `get_settings()`

Returns the settings array for the instance.

- **Return**: The settings array

### `get($field_id)`

Fetches the value of a specific setting field.

- **Return**: The value of the field.

## Example

Here's an example of how you could set up a new settings tab:

```php
require 'WooCommerceSettingsMagic/class-WooCommerceSettingsMagic.php';

$fields = [
    // Standard text field
    [
        'id' => 'text_field_custom_id',
        'type' => 'text',
        'title' => 'Text Field',
        'default' => 'Default Text',
        'placeholder' => 'Enter text', // Optional
        'custom_attributes' => ['readonly' => 'readonly'] // Optional
    ],
    
    // Textarea
    [
        'id' => 'textarea_field_custom_id',
        'type' => 'textarea',
        'title' => 'Textarea',
        'desc' => 'Abracadabra', // Optional
        'placeholder' => 'Now you see me!', // Optional
    ],
    
    // Number field
    [
        'id' => 'number_field_custom_id',
        'type' => 'number',
        'title' => 'Number Field',
        'default' => 10,
        'custom_attributes' => [
            'min' => 0,
            'max' => 100,
            'step' => 5,
            ]
        ],
        
    // Password field
    [
        'id' => 'password_field_custom_id',
        'type' => 'password',
        'title' => 'Password',
        'desc' => 'Use desc_tip to add a tooltip', // Optional
        'desc_tip' => true, // Optional, defaults to false
    ],

    // Date field
    [
        'id' => 'date_field_custom_id',
        'type' => 'date',
        'title' => 'Date',
    ],

    // Optional title field
    [
        'type' => 'title',
        'title' => 'More Settings',
        'desc' => 'This is a section description' // Optional
    ],

    // Color picker
    [
        'id' => 'color_field_custom_id',
        'type' => 'color',
        'title' => 'Color Picker',
    ],

    // Select dropdown
    [
        'id' => 'select_field_custom_id',
        'type' => 'select',
        'title' => 'Select Dropdown',
        'options' => [
            'option1' => 'Option 1',
            'option2' => 'Option 2',
        ],
        'default' => 'option1', // Optional, defaults to first option available if unset
    ],
    
    // Select2 dropdown
    [
        'id' => 'select2_field_custom_id',
        'type' => 'select',
        'title' => 'Select2 Dropdown',
        'options' => [
            'option1' => 'Option 1',
            'option2' => 'Option 2',
        ],
        'select2' => true,
        'default' => '', // Optional, defaults to '' if unset
    ],

    // Radio buttons
    [
        'id' => 'radio_field_custom_id',
        'type' => 'radio',
        'title' => 'Radio Buttons',
        'options' => [
            'a' => 'A',
            'b' => 'B',
        ],
        'default' => 'a', // Optional, defaults to first option available if unset
    ],

    // Single Checkbox
    [
        'id' => 'checkbox_field_custom_id',
        'type' => 'checkbox',
        'title' => 'Checkbox',
        'default' => 'no', // Optional, defaults to 'no' if unset
    ],

    // Checkbox group
    [
        'id' => 'checkbox1__custom_id', 
        'type' => 'checkbox', 
        'title' => 'Checkbox Group', // Checkbox group label
        'desc'	=> 'Checkbox 1', // Checkbox label
        'checkboxgroup' => 'start' // Optional
    ],
    [
        'id' => 'checkbox2__custom_id', 
        'type' => 'checkbox', 
        'desc'	=> 'Checkbox 2',
        'checkboxgroup' => 'middle', // Required if checkbox is in the middle of the group
    ],
    [
        'id' => 'checkbox3__custom_id', 
        'type' => 'checkbox', 
        'desc' => 'Checkbox 3',
        'checkboxgroup' => 'end' // Required if checkbox is at the end of the group
    ],			

    // Multi-select
    [
        'id' => 'multiselect_field_custom_id',
        'type' => 'multiselect',
        'title' => 'Multi-Select',
        'options' => [
            'apple' => 'Apple',
            'orange' => 'Orange',
        ],
        'default' => [], // Optional, can be a single string or array of strings. Defaults to an empty array if unset
    ],

    // Multi-select with Select2
    [
        'id' => 'multiselect2_field_custom_id',
        'type' => 'multiselect',
        'title' => 'Multi-Select with Select2',
        'options' => [
            'apple' => 'Apple',
            'orange' => 'Orange',
        ],
        'select2' => true,
        'default' => [] // Optional, can be a single string or array of strings. Defaults to an empty array if unset
    ],

];

// Using Static Method
$settings = WooCommerceSettingsMagic::presto('Custom Tab', 'custom_tab', $fields);

// Access the settings via your ids
if( $settings['radio_field_custom_id'] == 'a' ){
    // Do something
}
```

## Important Notes

1. The `fields` parameter should be well-structured, as the class performs internal validation.

2. The class caches settings upon instantiation to improve performance.
