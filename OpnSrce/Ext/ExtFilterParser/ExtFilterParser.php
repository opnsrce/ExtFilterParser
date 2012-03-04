<?php

namespace OpnSrce\Ext\ExtFilterParser;

/**
 * ExtFilter Parser is a class designed to quickly and easily parse data grid filters sent to the backend by an ExtJS 4 data grid.
 *
 * @package Opnsrce\Ext
 * @subpackage ExtFilterParser
 * @namespace \Opnsrce\Ext\ExtFilterParser
 * @author Levi Hackwith <levi.hackwith@gmail.com>
 * @copyright 2012 Levi Hackwith
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class ExtFilterParser
{

    /**
     * The parsed filters.
     *
     * @var string
     * @access protected
     * @see ExtFilterParser::parse()
     */
    protected $parsedFilters = array();

    /**
     * The filters to be parsed.
     *
     * @var type
     * @access protected
     * @see ExtFilterParser::setFilters()
     */
    protected $filters = '';

    /**
     * The $_GET or $_POST key that the filters will be stored in when passed from the frontend.
     *
     * @var string
     * @access protected
     * @see ExtFilterParser::pullFiltersFromGetOrPost()
     */
    protected $requestParam = 'filter';

    /**
     * This is the format that the value of all date filters will be parsed to.
     *
     * @var string
     * @access protected
     * @see ExtFilterParser::parseDateFilter
     */
    protected $dateFormat = 'Y-m-d';

    /**
     * Outputs the value of $this->filters when an instance of the class is converted to a string.
     *
     * Example of Use:
     *     $filterParser = new Opnsrce\Ext\ExtFilterParser\ExtFilterParser();
     *     $filterParser->setFilteres({"type":"date", "value":"2012-01-01", "field":"dateField", "comparison": "lt"});
     *     echo "Your filters are: $filterParser"; // Echos {"type":"date", "value":"2012-01-01", "field":"dateField", "comparison": "lt"}
     *
     * @access public
     * @link http://www.php.net/manual/en/language.oop5.magic.php#object.tostring PHP manual entry on __toString()
     * @return string
     */
    public function __toString()
    {
        return $this->filters;
    }

    /**
     * Gets the filters in their parsed state
     *
     * @access public
     * @see ExtFilterParser::$parsedFilters
     * @return array
     */
    public function getParsedFilters()
    {
        return $this->parsedFilters;
    }

    /**
     * Gets filters in their unparsed state.
     *
     * @access public
     * @see ExtFilterParser::$filters
     * @return string
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Sets the filters taht will be parsed.
     *
     * Example Use:
     *      $filterParser->setFilteres({"type":"date", "value":"2012-01-01", "field":"dateField", "comparison": "lt"});
     *
     * @access public
     * @param string $filters
     * @return OpnSrce\Ext\ExtFilterParser\ExtFilterParser
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;

        return $this;
    }
    /**
     * Gets the format that date filters will be parsed into.
     *
     * @access public
     * @see ExtFilterParser::$dateFormat
     * @return string
     */
    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    /**
     * Sets the format that date filters will be parsed into.
     *
     * $dateFormat must be formatted to meet one of the formats supported by PHP.
     *
     * @link http://us2.php.net/manual/en/function.date.php List of supported date formats
     * @access public
     * @param string $dateFormat
     * @see ExtFilterParser::$dateFormat
     * @return \OpnSrce\Ext\ExtFilterParser\ExtFilterParser
     */
    public function setDateFormat($dateFormat)
    {
        $this->dateFormat = $dateFormat;

        return $this;
    }

    /**
     * The class constructor.
     *
     * Example Use:
     *
     *      $extFilterParserWithDefaults = new ExtFilterParser();
     *      $extFilterParse = new ExtFilterParser('my_ext_filters', '/m/d/Y');
     *
     * @access public
     * @param string $requestParam (Optional) The parameter inside of $_GET or _POST that the filters are pulled from. Defaults to 'filter'.
     * @param string $dateFormat (Optional) The format that date filters' values will be converted to. Defaults to 'Y-m-d'.
     */
    public function __construct($requestParam = "filter", $dateFormat = "Y-m-d")
    {
        $this->requestParam = $requestParam;
        $this->dateFormat = $dateFormat;
        $filters = $this->pullFiltersFromGetOrPost();
        if(empty($filters) === FALSE) {
            $this->filters = $filters;
            $this->parse();
        }
    }

    /**
     * Pulls filters from $_GET or $_POST
     *
     * This method is called automatically from the class constructor. If a parameter inside of $_GET or $_POST matches the requestParam value, the filters
     * are automatically stored and parsed.
     *
     * @access protected
     * @see ExtFilterParser::$filters
     * @see ExtFilterParser::$requestParam
     * @see ExtFilterParser::parse()
     * @return string
     */
    protected function pullFiltersFromGetOrPost()
    {
        $filterJson = '';
        $filterFromGet = isset($_GET[$this->requestParam]) === TRUE ? $_GET[$this->requestParam] : '';
        $filterFromPost = isset($_POST[$this->requestParam]) === TRUE ? $_POST[$this->requestParam] : '';
        if (empty($filterFromGet) === FALSE) {
            $filterJson = $filterFromGet;
        }
        elseif (empty($filterFromPost) === FALSE) {
            $filterJson = $filterFromPost;
        }

        return $filterJson;
    }

    /**
     * Parses the Ext Filters
     *
     * @access public
     * @return OpnSrce\Ext\ExtFilterParser\ExtFilterParser
     * @throws \UnexpectedValueException If the filters being parsed are not valid
     */
    public function parse()
    {
        $decodedFilters = $this->decodeFilterJson($this->filters);
        foreach ($decodedFilters as $filter) {
            switch ($filter->type) {
                case 'numeric':
                    $this->parsedFilters[] = $this->parseComparisonFilter($filter);
                    break;
                case 'date':
                    $this->parsedFilters[] = $this->parseDateFilter($filter);
                    break;
                case 'list':
                    $this->parsedFilters[] = $this->parseListFilter($filter);
                    break;
                case 'string':
                    $this->parsedFilters[] = $this->parseStringFilter($filter);
                    break;
                default:
                    throw new \UnexpectedValueException(__METHOD__ . " Unknown filter type '$filter->type'");
            }
        }

        return $this;
    }

    /**
     * Parses the Ext Filters and then converts them into WHERE clauses for the passed SQL Query.
     *
     * @access public
     * @param string $query The SQL query to attach the WHERE clause to
     * @return string
     */
    public function parseIntoQuery($query)
    {
        $this->parse();
        $whereClause = 'WHERE 1=1';
        $clauses = array();

        foreach ($this->parsedFilters as $filter) {
           $clauses[] = $filter['expression'] . ' ' . $filter['value'];
        }
        if(empty($clauses) === FALSE) {
            $whereClause = 'WHERE ' . implode(' AND ', $clauses);
        }
        $query .= " $whereClause";

        return $query;

    }

    /**
     * Validates Decodes the passed in JSON into an instance of StdClass using json_decode.
     *
     * @access protected
     * @param string $filter_json The JSON to be decoded
     * @return StdClass
     * @throws \InvalidArgumentException If the passed in JSON is not valid
     */
    protected function decodeFilterJson($filter_json)
    {
        $decodedFilters = array();
        if (empty($filter_json) === FALSE) {
            $decodedFilters = json_decode($filter_json);
            if ($decodedFilters === NULL) {
                throw new \InvalidArgumentException(__METHOD__ . " Expects first parameter to be a valid JSON string");
            }
            if (!is_array($decodedFilters)) {
                $decodedFilters = array($decodedFilters);
            }
        }

        return $decodedFilters;
    }

    /**
     * Translates Ext's custom comparison types into the standard '>', '<', and '=' symbols.
     *
     * @access protected
     * @param string $comparison_operator The comparison operator being translated
     * @return string
     * @throws \UnexpectedValueException If the comparison operator is not one of the expected values
     */
    protected function translateComparisonOperator($comparison_operator)
    {
        $operator = '';
        switch ($comparison_operator) {
            case 'lt':
                $operator = '<';
                break;
            case 'gt':
                $operator = '>';
                break;
            case 'eq':
                $operator = '=';
                break;
            default:
                throw new \UnexpectedValueException(__METHOD__ . " Invalid comparison operator '$comparison_operator'");
        }

        return $operator;
    }

    /**
     * Parses a comparison filter.
     *
     * @access protected
     * @param stdClass $filter The filter being parsed
     * @return array
     */
    protected function parseComparisonFilter($filter)
    {
        $comparisonOperator = $this->translateComparisonOperator($filter->comparison);
        if (!is_numeric($filter->value)) {
            $filter->value = "'$filter->value'";
        }

        return array(
            'expression' => "$filter->field $comparisonOperator",
            'value' => $filter->value
        );
    }

    /**
     * Parses a Date Filter.
     *
     * @access protected
     * @param stdClass $filter The filter being parsed
     * @return array
     */
    protected function parseDateFilter($filter)
    {
        $value = $filter->value;
        if ($value == '0000-00-00') {
            $value = '';
        }
        $timestamp = strtotime($value);
        if ($timestamp !== FALSE) {
            $value = date($this->dateFormat, $timestamp);
        }
        $filter->value = $value;

        return $this->parseComparisonFilter($filter);
    }

    /**
     * Parses a String Filter.
     *
     * @access protected
     * @param stdClass $filter The filter being parsed
     * @return array
     */
    protected function parseStringFilter($filter)
    {
        return array(
            'expression' => "$filter->field LIKE",
            'value' => "'%$filter->value%'"
        );
    }

    /**
     * Parses a List Filter.
     *
     * @access protected
     * @param stdClass $filter The filter being parsed
     * @return array
     */
    protected function parseListFilter($filter)
    {
        if (!is_array($filter->value)) {
            $filter->value = explode(',', $filter->value);
        }
        return array(
            'expression' => "$filter->field IN",
            'value' => "('" . implode("','", $filter->value) . "')"
        );
    }

}

?>