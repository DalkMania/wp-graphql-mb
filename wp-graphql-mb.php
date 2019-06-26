<?php
/**
 * Plugin Name: WP GraphQL Meta Box Custom Fields
 * Description: Exposes all registered Meta Box Custom Fields to the WPGraphQL EndPoint.
 * Author: Niklas Dahlqvist
 * Author URI: https://www.niklasdahlqvist.com
 * Version: 0.7
 * License: GPL2+
 */

namespace WPGraphQL\Extensions;

use WPGraphQL\Types;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\WPGraphQL\Extensions\MB')) {
    class MB
    {
        /**
        * List of media fields to filter.
        *
        * @var array
        */
        protected $media_fields = [
            'media',
            'file',
            'file_upload',
            'file_advanced',
            'image',
            'image_upload',
            'image_advanced',
            'plupload_image',
            'thickbox_image',
        ];

        public function __construct()
        {
            $this->add_meta_boxes_to_graphQL();
        }

        public function add_meta_boxes_to_graphQL()
        {
            $types = array_merge(
                $this->get_types(),
                $this->get_types('taxonomy')
            );

            foreach ($types as $type => $object) {
                if (isset($object->graphql_single_name)) {
                    add_action('graphql_register_types', function ($fields) use ($type) {
                        return $this->add_meta_fields($fields, $type);
                    });
                }
            }
        }

        public function add_meta_fields($fields, $object_type)
        {
            $boxes = array_merge(
                $this->get_post_type_meta_boxes($object_type),
                $this->get_term_meta_boxes($object_type)
            );

            foreach ($boxes as $box) {
                foreach ($box->post_types as $type) {
                    $post_type_object = get_post_type_object( $type );
                    foreach ($box->fields as $field) {
                        $field_name = self::_graphql_label($field['id']);
                        if (!in_array($field['type'], $this->media_fields) && $field['clone'] == false && $field['multiple'] == false) {
                            register_graphql_field( $post_type_object->graphql_single_name, $field_name, [
                                'type' => Types::string(),
                                'description' => $field['desc'],
                                'resolve' => function ($object) use ($object_type, $field) {
                                    if ('post' === $object_type || in_array($object_type, get_post_types(), true)) {
                                        return !empty(get_post_meta($object->ID, $field['id'], true)) ? get_post_meta($object->ID, $field['id'], true) : null;
                                    }
                                    if ('term' === $object_type || in_array($object_type, get_taxonomies(), true)) {
                                        return !empty(get_term_meta($object->term_id, $field['id'], true)) ? get_term_meta($object->term_id, $field['id'], true) : null;
                                    }
                                    if ('user' === $object_type) {
                                        return !empty(get_user_meta($object->ID, $field['id'], true)) ? get_user_meta($object->ID, $field['id'], true) : null;
                                    }
                                },
    
                            ]);
                        }
                        if (!in_array($field['type'], $this->media_fields) && ($field['clone'] == true || $field['multiple'] == true)) {
                            register_graphql_field( $post_type_object->graphql_single_name, $field_name, [
                                'type' => Types::list_of(Types::string()),
                                'description' => $field['desc'],
                                'resolve' => function ($object) use ($object_type, $field) {
                                    if ('post' === $object_type || in_array($object_type, get_post_types(), true)) {
                                        return !empty(get_post_meta($object->ID, $field['id'], false)) ? get_post_meta($object->ID, $field['id'], false) : null;
                                    }
                                    if ('term' === $object_type || in_array($object_type, get_taxonomies(), true)) {
                                        return !empty(get_term_meta($object->term_id, $field['id'], false)) ? get_term_meta($object->term_id, $field['id'], false) : null;
                                    }
                                    if ('user' === $object_type) {
                                        return !empty(get_user_meta($object->ID, $field['id'], false)) ? get_user_meta($object->ID, $field['id'], false) : null;
                                    }
                                },
    
                            ]);
                        }
                        if (in_array($field['type'], $this->media_fields)) {
                            register_graphql_field( $post_type_object->graphql_single_name, $field_name, [
                                'type' => Types::list_of(Types::post_object('attachment')),
                                'description' => $field['desc'],
                                'resolve' => function ($object) use ($object_type, $field) {
                                    if ('post' === $object_type || in_array($object_type, get_post_types(), true)) {
                                        $values = !empty(get_post_meta($object->ID, $field['id'], false)) ? get_post_meta($object->ID, $field['id'], false) : null;
                                    }
                                    if ('term' === $object_type || in_array($object_type, get_taxonomies(), true)) {
                                        $values = !empty(get_term_meta($object->term_id, $field['id'], false)) ? get_term_meta($object->term_id, $field['id'], false) : null;
                                    }
                                    if ('user' === $object_type) {
                                        $values = !empty(get_user_meta($object->ID, $field['id'], false)) ? get_user_meta($object->ID, $field['id'], false) : null;
                                    }

                                    if ($values != null) {
                                        $images = [];
                                        foreach ($values as $value) {
                                            $images[] = \WP_Post::get_instance($value);
                                        }
                                        return $images;
                                    } else {
                                        return null;
                                    }
                                },
    
                            ]);
                        
                        }
                    }
                }
            }

            return $fields;
        }

        /**
         * Get metaboxes .
         *
         * @param array $object Post object.
         *
         * @return array
         */
        public function get_post_type_meta_boxes($type)
        {
            $meta_boxes = \rwmb_get_registry('meta_box')->get_by(['object_type' => 'post']);
            foreach ($meta_boxes as $key => $meta_box) {
                if (!in_array($type, $meta_box->post_types, true)) {
                    unset($meta_boxes[$key]);
                }
            }
            return $meta_boxes;
        }

        /**
         * Get term meta boxes.
         *
         * @param array $object Term object.
         *
         * @return array
         */
        public function get_term_meta_boxes($type)
        {
            $output = [];
            if (!class_exists('MB_Term_Meta_Box')) {
                return $output;
            }

            $meta_boxes = \rwmb_get_registry('meta_box')->get_by([
                'object_type' => 'term',
            ]);

            return $meta_boxes;
        }

        /**
         * Get supported supported post types and / or taxonomies.
         *
         * @param string $type 'post' or 'taxonomy'.
         *
         * @return array
         */
        protected function get_types($type = 'post')
        {
            $types = get_post_types([], 'objects');
            if ('taxonomy' === $type) {
                $types = get_taxonomies([], 'objects');
            }

            return $types;
        }

        /**
         * Utility function for formatting a string to be compatible with GraphQL labels (camelCase with lowercase first letter)
         *
         * @param $input
         *
         * @return mixed|string
         */
        public static function _graphql_label($input)
        {
            $graphql_label = str_ireplace('_', ' ', $input);
            $graphql_label = ucwords($graphql_label);
            $graphql_label = str_ireplace(' ', '', $graphql_label);
            $graphql_label = lcfirst($graphql_label);

            return $graphql_label;
        }
    }
}

add_action('init_graphql_request', function () {
    new MB;
});
