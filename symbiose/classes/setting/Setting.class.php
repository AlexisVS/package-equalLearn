<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace symbiose\setting;

class Setting extends \core\setting\Setting {


    public static function getColumns() {
        return [

            'setting_values_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'symbiose\setting\SettingValue',
                'foreign_field'     => 'setting_id',
                'sort'              => 'asc',
                'order'             => 'name',
                'description'       => 'List of values related to the setting.'
            ],

            'setting_sequences_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'symbiose\setting\SettingSequence',
                'foreign_field'     => 'setting_id',
                'sort'              => 'asc',
                'order'             => 'name',
                'description'       => 'List of sequences related to the setting.'
            ]

        ];
    }

    public static function fetch_and_add(string $package, string $section, string $code, $increment=null, array $selector=[], string $lang='en') {
        $result = null;

        $providers = \eQual::inject(['orm']);
        /** @var \equal\orm\ObjectManager */
        $orm = $providers['orm'];

        $settings_ids = $orm->search(self::getType(), [
            ['package', '=', $package],
            ['section', '=', $section],
            ['code', '=', $code]
        ]);

        if($settings_ids > 0 && count($settings_ids)) {

            $settings = $orm->read(self::getType(), $settings_ids, ['type', 'setting_sequences_ids']);

            if($settings > 0 && count($settings)) {
                // #memo - there should be exactly one setting matching the criterias
                $setting = array_pop($settings);

                $setting_sequence_id = 0;
                $setting_sequences = $orm->read(SettingSequence::getType(), $setting['setting_sequences_ids'], ['id', 'organisation_id']);
                if($setting_sequences > 0) {
                    foreach($setting_sequences as $sequence) {
                        if(intval($sequence['organisation_id']) == intval($selector['organisation_id'] ?? 0)) {
                            $setting_sequence_id = $sequence['id'];
                            break;
                        }
                    }
                }
                if($setting_sequence_id > 0) {
                    $result = $orm->fetchAndAdd(SettingSequence::getType(), $setting_sequence_id, 'value', $increment);
                }
            }
        }

        return $result;
    }

    /**
     * Retrieve the value of a given setting.
     *
     * @param   $package    Package to which the setting relates to.
     * @param   $section    Specific section within the package.
     * @param   $code       Unique code of the setting within the given package and section.
     * @param   $default    (optional) Default value to return if setting is not found.
     * @param   $selector   (optional) Retrieve the specific value assigned to a given user.
     * @param   $lang       (optional) Lang in which to retrieve the value (for multilang settings).
     *
     * @return  mixed       Returns the value of the target setting or null if the setting parameter is not found. The type of the returned var depends on the setting's `type` field.
     */
    public static function get_value(string $package, string $section, string $code, $default=null, array $selector=[], string $lang='en') {
        $result = $default;

        // #memo - we use a dedicated cache since several o2m fields are involved and we want to prevent loading the same value multiple times in a same thread
        $index = $package.'.'.$section.'.'.$code.'.'.implode('.', array_values($selector)).'.'.$lang;
        if(!isset($GLOBALS['_symbiose_setting_cache'])) {
            $GLOBALS['_symbiose_setting_cache'] = [];
        }

        if(isset($GLOBALS['_symbiose_setting_cache'][$index])) {
            return $GLOBALS['_symbiose_setting_cache'][$index];
        }

        $providers = \eQual::inject(['orm']);
        /** @var \equal\orm\ObjectManager */
        $om = $providers['orm'];

        $settings_ids = $om->search(self::getType(), [
            ['package', '=', $package],
            ['section', '=', $section],
            ['code', '=', $code]
        ]);

        if($settings_ids > 0 && count($settings_ids)) {

            $settings = $om->read(self::getType(), $settings_ids, ['type', 'is_multilang', 'setting_values_ids']);

            if($settings > 0 && count($settings)) {
                // #memo - there should be exactly one setting matching the criterias
                $setting = array_pop($settings);

                $values_lang = constant('DEFAULT_LANG');
                if($setting['is_multilang']) {
                    $values_lang = $lang;
                }

                $setting_values = $om->read(SettingValue::getType(), $setting['setting_values_ids'], ['user_id', 'value'], $values_lang);
                if($setting_values > 0) {
                    $value = null;
                    // #memo - by default settings values are sorted on user_id (which can be null), so first value is the default one
                    foreach($setting_values as $setting_value) {
                        $value = $setting_value['value'];
                        if(isset($selector['user_id']) && $setting_value['user_id'] == $selector['user_id']) {
                            break;
                        }
                    }
                    if(!is_null($value)) {
                        $result = $value;
                        settype($result, $setting['type']);
                    }
                }
            }
        }

        $GLOBALS['_symbiose_setting_cache'][$index] = $result;
        return $result;
    }

    /**
     * Update the value of a given setting.
     *
     * @param   $package    Package to which the setting relates to.
     * @param   $section    Specific section within the package.
     * @param   $code       Unique code of the setting within the given package and section.
     * @param   $value      The new value that has to be assigned to the setting.
     * @param   $selector   (optional) Target the specific value assigned to a given user.
     *
     * @return  void
     */
    public static function set_value(string $package, string $section, string $code, $value, array $selector=[], $lang='en') {
        $providers = \eQual::inject(['orm']);
        $om = $providers['orm'];

        $sections_ids = $om->search(SettingSection::getType(), ['code', '=', $section]);
        if(!count($sections_ids)) {
            // section does not exist yet
            $sections_ids = (array) $om->create(SettingSection::getType(), ['name' => $section, 'code' => $section]);
        }
        $section_id = reset($sections_ids);

        $settings_ids = $om->search(self::getType(), [
            ['package', '=', $package],
            ['section_id', '=', $section_id],
            ['code', '=', $code]
        ]);

        if(!count($settings_ids)) {
            // setting does not exist yet
            $settings_ids = (array) $om->create(Setting::getType(), ['package' => $package, 'section_id' => $section_id, 'code' => $code]);
        }
        $setting_id = reset($settings_ids);

        $domain = [ ['setting_id', '=', $setting_id] ];

        foreach($selector as $field => $value) {
            $domain[] = [$field, '=', $value];
        }

        $settings_values_ids = $om->search(SettingValue::getType(), $domain);
        if(!count($settings_values_ids)) {
            $values = ['setting_id' => $setting_id, 'value' => $value];
            foreach($selector as $field => $value) {
                $values[$field] = $value;
            }
            // value does not exist yet: create a new value
            $om->create(SettingValue::getType(), $values, $lang);
        }
        else {
            // update existing value
            $om->update(SettingValue::getType(), $settings_values_ids, ['value' => $value], $lang);
        }

        // #memo - we use a dedicated cache since several o2m fields are involved and we want to prevent loading the same value multiple times in a same thread
        $index = $package.'.'.$section.'.'.$code.'.'.implode('.', array_values($selector)).'.'.$lang;
        if(!isset($GLOBALS['_symbiose_setting_cache'])) {
            $GLOBALS['_symbiose_setting_cache'] = [];
        }
        $GLOBALS['_symbiose_setting_cache'][$index] = $value;
    }

}
