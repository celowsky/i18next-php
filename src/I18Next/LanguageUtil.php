<?php
/**
 * Created by PhpStorm.
 * User: pkly
 * Date: 20.09.2019
 * Time: 08:32
 */

namespace I18Next;


class LanguageUtil {
    private $_options                           =   [];
    private $_whitelist                         =   false;

    public function __construct(array $options = []) {
        $this->_options = $options;

        $this->_whitelist = $this->_options['whitelist'] ?? false;
    }

    public function getScriptPartFromCode(string $code) {
        if (!$code || mb_strpos($code, '-') === false)
            return null;

        $p = explode('-', $code);
        if (count($p))
            return null;
        array_pop($p);
        return $this->formatLanguageCode(implode('-', $p));
    }

    public function getLanguagePartFromCode(string $code) {
        if (!$code || mb_strpos($code, '-') === false)
            return $code;

        $p = explode('-', $code);
        return $this->formatLanguageCode($p[0]);
    }

    public function formatLanguageCode(string $code) {
        if (mb_strpos($code, '-') !== false) {
            $specialCases = ['hans', 'hant', 'latn', 'cyrl', 'cans', 'mong', 'arab'];
            $p = explode('-', $code);

            if ($this->_options['lowerCaseLng'] ?? false) {
                $p = array_map(function($o) {
                    return mb_strtolower($o);
                }, $p);
            }
            else if (count($p) === 2) {
                $p[0] = mb_strtolower($p[0]);
                $p[1] = mb_strtolower($p[1]);

                if (in_array(mb_strtolower($p[1]), $specialCases))
                    $p[1] = Utils\capitalize(mb_strtolower($p[1]));
            }
            else if (count($p) === 3) {
                $p[0] = mb_strtolower($p[0]);

                // if length is 2 guess it's a country
                if (mb_strlen($p[1]) === 2)
                    $p[1] = mb_strtoupper($p[1]);

                if ($p[0] !== 'sgn' && mb_strlen($p[2]) === 2)
                    $p[2] = mb_strtoupper($p[2]);

                if (in_array(mb_strtolower($p[1]), $specialCases))
                    $p[1] = Utils\capitalize(mb_strtolower($p[1]));

                if (in_array(mb_strtolower($p[2]), $specialCases))
                    $p[2] = Utils\capitalize(mb_strtolower($p[2]));
            }

            return implode('-', $p);
        }

        return ($this->_options['cleanCode'] ?? false) || ($this->_options['lowerCaseLng'] ?? false) ? mb_strtolower($code) : $code;
    }

    public function isWhitelisted(string $code) {
        if ($this->_options['load'] ?? null === 'languageOnly' || $this->_options['nonExplicitWhitelist'] ?? false) {
            $code = $this->getLanguagePartFromCode($code);
        }

        return $this->_whitelist === false || !count($this->_whitelist) || in_array($code, $this->_whitelist);
    }

    public function getFallbackCodes($fallbacks, string $code) {
        if (!$fallbacks)
            return [];

        if (is_string($fallbacks))
            $fallbacks = [$fallbacks];


    }
}