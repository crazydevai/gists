<?php

// create a custom WooCommerce settings tab
add_filter('woocommerce_get_settings_pages', 'custom_discount_settings_tab');
function custom_discount_settings_tab($settings_tabs) {
    if (!class_exists('WC_Settings_Custom_Discount')) {
        class WC_Settings_Custom_Discount extends WC_Settings_Page {
            public function __construct() {
                $this->id    = 'custom_discount';
                $this->label = __('Discounts', 'woocommerce');

                add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_page'], 20);
                add_action("woocommerce_settings_{$this->id}", [$this, 'output']);
                add_action("woocommerce_settings_save_{$this->id}", [$this, 'save']);
            }

            public function get_settings() {
                $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
                $products   = wc_get_products(['limit' => -1]);

                $cat_options = [];
                foreach ($categories as $cat) {
                    $cat_options[$cat->term_id] = $cat->name;
                }

                $product_options = [];
                foreach ($products as $product) {
                    $product_options[$product->get_id()] = $product->get_name();
                }

                return [
                    [
                        'title' => __('Category Discounts', 'woocommerce'),
                        'type'  => 'title',
                        'id'    => 'custom_discount_category_title'
                    ],
                    [
                        'title'    => __('Select Categories', 'woocommerce'),
                        'desc'     => __('Apply discount to these categories', 'woocommerce'),
                        'id'       => 'custom_discount_categories',
                        'type'     => 'multiselect',
                        'class'    => 'wc-enhanced-select',
                        'options'  => $cat_options,
                        'desc_tip' => true
                    ],
                    [
                        'title'    => __('Category Discount (%)', 'woocommerce'),
                        'id'       => 'custom_discount_percentage',
                        'type'     => 'number',
                        'custom_attributes' => [
                            'min' => '0',
                            'max' => '100',
                            'step' => '1'
                        ],
                        'default'  => '0',
                        'desc_tip' => true,
                        'desc'     => __('Enter a value from 0 to 100.'),
                    ],
                    [
                        'type' => 'sectionend',
                        'id'   => 'custom_discount_category_title'
                    ],
                    [
                        'title' => __('Product Discounts', 'woocommerce'),
                        'type'  => 'title',
                        'id'    => 'custom_discount_product_title'
                    ],
                    [
                        'title'    => __('Select Products', 'woocommerce'),
                        'desc'     => __('Apply discount to specific products', 'woocommerce'),
                        'id'       => 'custom_discount_products',
                        'type'     => 'multiselect',
                        'class'    => 'wc-enhanced-select',
                        'options'  => $product_options,
                        'desc_tip' => true
                    ],
                    [
                        'title'    => __('Product Discount (%)', 'woocommerce'),
                        'id'       => 'custom_discount_product_percentage',
                        'type'     => 'number',
                        'custom_attributes' => [
                            'min' => '0',
                            'max' => '100',
                            'step' => '1'
                        ],
                        'default'  => '0',
                        'desc_tip' => true,
                        'desc'     => __('Enter a value from 0 to 100.'),
                    ],
                    [
                        'type' => 'sectionend',
                        'id'   => 'custom_discount_product_title'
                    ]
                ];
            }

            public function output() {
                WC_Admin_Settings::output_fields($this->get_settings());
            }

            public function save() {
                WC_Admin_Settings::save_fields($this->get_settings());
            }
        }
    }

    $settings_tabs[] = new WC_Settings_Custom_Discount();
    return $settings_tabs;
}

// Apply dynamic discounts on selected products or product categories
add_filter('woocommerce_product_get_price', 'apply_custom_discount_to_product_display_price', 20, 2);
add_filter('woocommerce_product_get_sale_price', 'apply_custom_discount_to_product_display_price', 20, 2);

function apply_custom_discount_to_product_display_price($price, $product) {
    // Avoid infinite loop if price already modified
    if (is_admin() && !defined('DOING_AJAX')) return $price;

    $product_id = $product->get_id();

    $category_ids = (array) get_option('custom_discount_categories', []);
    $category_discount = floatval(get_option('custom_discount_percentage', 0));

    $product_ids = (array) get_option('custom_discount_products', []);
    $product_discount = floatval(get_option('custom_discount_product_percentage', 0));

    $original_price = $product->get_regular_price();
    if (!$original_price || $original_price <= 0) return $price;

    $discounted_price = $original_price;

    // Product-specific discount takes priority
    if (!empty($product_ids) && in_array($product_id, $product_ids)) {
        $discounted_price = $original_price * (1 - $product_discount / 100);
    }
    // Otherwise check for category-based discount
    elseif (!empty($category_ids)) {
        $product_cats = wc_get_product_term_ids($product_id, 'product_cat');
        if (!empty(array_intersect($product_cats, $category_ids))) {
            $discounted_price = $original_price * (1 - $category_discount / 100);
        }
    }

    return round($discounted_price, wc_get_price_decimals());
}
