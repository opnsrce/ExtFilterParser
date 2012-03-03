<?php

namespace OpnSrce\Ext\ExtFilterParser;

/**
 * ExtFilter Parser is a class designed to quickly and easily parse data grid filters sent to the backend by an ExtJS 4 data grid.
 *
 * @author Levi Hackwith <levi.hackwith@gmail.com>
 * @copyright 2012 Levi Hackwith
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @package Opnsrce\Ext
 * @namespace \Opnsrce\Ext\ExtFilterParser
 * @subpackage ExtFilterParser
 */
class ExtFilterParser
{

    /** @var string the title to use in the header */
    protected $parsedFilters = array();

    /** @var string Stores the filters passed to {@link setFilters}. */
    protected $filters = '';

    /** @var string The name of the $_GET or $_POST parameter that {@link pullFilterJsonFromRequest} looks for. */
    protected $requestParam = 'filter';

    /** @var string This is the format that the value of all date filters will be translated to */
    protected $dateFormat = 'Y-m-d';

    /**
     * Outputs the value of $this->filters when Opnsrce\Ext\ExtFilterParser\ExtFilterParser is converted to a string
     *
     * Example of Use:
     *     $filterParser = new Opnsrce\Ext\ExtFilterParser\ExtFilterParser();
     *     $filterParser->setFilteres({"type":"date", "value":"2012-01-01", "field":"dateField", "comparison": "lt"});
     *     echo "Your filters are: $filterParser"; // Echos {"type":"date", "value":"2012-01-01", "field":"dateField", "comparison": "lt"}
     *
     * @return string
     */
    public function __toString()
    {
        return $this->filters;
    }

    /**
     *
     * @return array
     */
    public function getParsedFilters()
    {
        return $this->parsedFilters;
    }

    /**
     *
     * @return string
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     *
     * @return string
     */
    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    public function setDateFormat($dateFormat)
    {
        $this->dateFormat = $dateFormat;

        return $this;
    }

    /**
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
     * ExtFilterParser
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
     * @access protected
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
     * Parses the Ext Filters and stores the results in {@link $parsedFilters}.
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
     * Validates Decodes the passed in JSON into an instance of StdClass using json_decode
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
     * Translates Ext's custom comparison type into the standard '>', '<', and '=' symbols
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
     * Parses a comparison filter
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
     * Parses a Date Filter
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
     * Parses a String Filter
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
     * Parses a List Filter
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