<?php
/**
 * Created by PhpStorm.
 * User: pkly
 * Date: 18.09.2019
 * Time: 14:45
 */

namespace I18Next;

class Interpolator {
    /**
     * @var array
     */
    private $_options                           =   [];

    /**
     * @var \Closure|null
     */
    private $_format                            =   null;

    /**
     * @var \Closure|null
     */
    private $_escape                            =   null;

    /**
     * @var bool
     */
    private $_escapeValue                       =   true;

    /**
     * @var bool
     */
    private $_useRawValueToEscape               =   false;

    /**
     * @var string
     */
    private $_prefix                            =   '{{';

    /**
     * @var string
     */
    private $_suffix                            =   '}}';

    /**
     * @var string
     */
    private $_formatSeparator                   =   ',';

    /**
     * @var string
     */
    private $_unescapePrefix                    =   '-';

    /**
     * @var string
     */
    private $_unescapeSuffix                    =   '';

    /**
     * @var string|null
     */
    private $_nestingPrefix                     =   null;

    /**
     * @var string|null
     */
    private $_nestingSuffix                     =   null;

    /**
     * @var int
     */
    private $_maxReplaces                       =   100;

    /**
     * @var string
     */
    private $_regexp                            =   '';

    /**
     * @var string
     */
    private $_regexpUnescape                    =   '';

    /**
     * @var string
     */
    private $_nestingRegexp                     =   '';

    public function __construct(array $options = []) {
        // TODO: Create logger for component

        $this->_options = $options;
        $this->_format = $options['interpolation']['format'] ?? function ($value, $format, $lng) { return $value; };
    }

    public function init(array $options = []) {
        if (!isset($options['interpolation']))
            $options['interpolation'] = [
                'escapeValue'       =>  true
            ];

        $iOpts = $options['interpolation'];

        $this->_escape = $iOpts['escape'] ?? \Closure::fromCallable('\I18Next\Utils\escape');
        $this->_escapeValue = $iOpts['escapeValue'] ?? true;
        $this->_useRawValueToEscape = $iOpts['useRawValueToEscape'] ?? false;

        $this->_prefix = $iOpts['prefix'] ?? false ? Utils\regexEscape($iOpts['prefix']) : $iOpts['prefixEscaped'] ?? '{{';
        $this->_suffix = $iOpts['suffix'] ?? false ? Utils\regexEscape($iOpts['suffix']) : $iOpts['suffixEscaped'] ?? '}}';

        $this->_formatSeparator = $iOpts['formatSeparator'] ?? ',';

        $this->_unescapePrefix = $iOpts['unescapeSuffix'] ?? false ? '' : $iOpts['unescapePrefix'] ?? '-';
        $this->_unescapeSuffix = $this->_unescapePrefix ? '' : $iOpts['unescapeSuffix'] ?? '';

        $this->_nestingPrefix = $iOpts['nestingPrefix'] ?? false ? Utils\regexEscape($iOpts['nestingPrefix']) : $iOpts['nestingPrefixEscaped'] ?? Utils\regexEscape('$t(');
        $this->_nestingSuffix = $iOpts['nestingSuffix'] ?? false ? Utils\regexEscape($iOpts['nestingSuffix']) : $iOpts['nestingSuffixEscaped'] ?? Utils\regexEscape(')');

        $this->_maxReplaces = $iOpts['maxReplaces'] ?? 1000;

        $this->resetRegExp();
    }

    public function reset() {
        if ($this->_options)
            $this->init($this->_options);
    }

    public function resetRegExp() {
        $this->_regexp = '/' . $this->_prefix . '(.+?)' . $this->_suffix . '/';
        $this->_regexpUnescape = '/' . $this->_prefix . $this->_unescapePrefix . '(.+?)' . $this->_unescapeSuffix . $this->_suffix . '/';
        $this->_nestingRegexp = '/' . $this->_nestingPrefix . '(.+?)' . $this->_nestingSuffix . '/';
    }

    public function interpolate(string $str, $data, $lng = null, array $options = []): string {
        $defaultData = $this->_options['interpolation']['defaultVariables'] ?? [];

        $combinedData = array_merge($defaultData, $data);

        $regexSafe = function($v) {
            return preg_replace('/\$/g', '$$$$', $v);
        };

        $handleFormat = function($key) use (&$combinedData, $lng) {
            if (mb_strpos($key, $this->_formatSeparator) === false)
                return Utils\getPath($combinedData, $key);

            $p = explode($this->_formatSeparator, $key);
            $k = trim(array_shift($p));
            $f = trim(implode($this->_formatSeparator, $p));

            return call_user_func($this->_format, Utils\getPath($combinedData, $k), $f, $lng);
        };

        $this->resetRegExp();

        $missingInterpolationHandler = $options['missingInterpolationHandler'] ?? $this->_options['missingInterpolationHandler'] ?? null;

        $match = [];
        $replaces = 0;

        do {
            preg_match($this->_regexpUnescape, $str, $match);
            if (!$match)
                break;

            $value = $handleFormat(trim($match[1] ?? ''));
            if (!$value) {
                if (is_callable($missingInterpolationHandler)) {
                    $temp = $missingInterpolationHandler($str, $match, $options);
                    $value = is_string($temp) ? $temp : '';
                }
                else {
                    // TODO: Add logger warn missed to pass in variable match[1] for interpolating str
                    $value = '';
                }
            }
            else if (is_string($value) && !$this->_useRawValueToEscape) {
                // TODO: This is basically some js bullshit that goes like '' + {} (presumably to transform things to [object Object] or [object Array])
                $value = (string)$value;
            }

            $str = str_replace($match[0], $regexSafe($value), $str);
            $replaces++;

            if ($replaces >= $this->_maxReplaces)
                break;
        } while (!empty($matches));

        $match = [];
        $replaces = 0;

        do {
            preg_match($this->_regexp, $str, $match);
            if (!$match)
                break;

            $value = $handleFormat(trim($match[1] ?? ''));
            if (!$value) {
                if (is_callable($missingInterpolationHandler)) {
                    $temp = $missingInterpolationHandler($str, $match, $options);
                    $value = is_string($temp) ? $temp : '';
                }
                else {
                    // TODO: Add logger warn missed to pass in variable match[1] for interpolating str
                    $value = '';
                }
            }
            else if (is_string($value) && !$this->_useRawValueToEscape) {
                // TODO: This is basically some js bullshit that goes like '' + {} (presumably to transform things to [object Object] or [object Array])
                $value = (string)$value;
            }

            $value = $this->_escapeValue ? $regexSafe(call_user_func($this->_escape, $value)) : $regexSafe($value);
            $str = str_replace($match[0], $value, $str);
            $replaces++;

            if ($replaces >= $this->_maxReplaces)
                break;
        } while (!empty($matches));

        return $str;
    }

    public function nest(string $str, callable $fc, array $options = []) {
        $clonedOptions = $options;
        $clonedOptions['applyPostProcessor'] = false;


        $handleHasOptions = function (string $key, array $inheritedOptions) use (&$clonedOptions) {
            if (mb_strpos($key, ',') === false)
                return $key;

            $p = explode(',', $key);
            $key = array_shift($p);
            $optionsString = str_replace("'", '"',$this->interpolate(implode(',', $p), $clonedOptions));

            try {
                $clonedOptions = json_decode($optionsString);
                if (json_last_error() !== JSON_ERROR_NONE)
                    throw new \Exception();

                if ($inheritedOptions)
                    $clonedOptions = array_merge($inheritedOptions, $clonedOptions);
            }
            catch (\Exception $e) {
                // TODO: logger error failed parsing options string in nesting for key $key
            }

            return $key;
        };

        $match = [];

        // regular escape on demand
        do {
            preg_match($this->_nestingRegexp, $str, $match);
            if (!$match)
                break;

            $value = $fc($handleHasOptions(trim($match[1]), $clonedOptions), $clonedOptions);

            // is only the nesting key (key1 = '$(key2)') return the value without stringify
            if ($value && $match[0] === $str && !is_string($value)) return $value;

            if (!is_string($value))
                $value = (string)$value;

            if (!$value) {
                // TODO: logger warn missed to resolve match1 for nesting str
                $value = '';
            }

            $str = str_replace($match[0], $value, $str);

        } while (!empty($match));

        return $str;
    }
}