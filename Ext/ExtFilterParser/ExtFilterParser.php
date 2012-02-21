<?php

namespace Ext\ExtFilterParser;

class ExtFilterParser {

    /**
     * Stores the filters after they've been parsed with {@link parse}.
     * @var array
     */
    private $parsedFilters = array();

    /**
     * Stores the filters passed to {@link setFilters}.
     * @var string
     */
    private $filters = '';

    /**
     * The name of the $_GET or $_POST parameter that {@link pullFilterJsonFromRequest} looks for.
     * @var type
     */
    private $requestParam = 'filter';

    /**
     *
     * @return string
     */
    public function __toString() {
        return $this->filters;
    }

    /**
     *
     * @return array
     */
    public function getParsedFilters() {
        return $this->parsedFilters;
    }

    /**
     *
     * @return string
     */
    public function getFilters() {
        return $this->filters;
    }

    /**
     *
     * @param string $filters
     * @return \TestApp\Services\Ext\ExtFilterParser
     */
    public function setFilters($filters) {
        $this->filters = $filters;
        return $this;
    }

    /**
     * ExtFilterParser
     * @param \Symfony\Component\HttpFoundation\Request $request Instance of Symfony2's request object.
     * @link api.symfony.com/2.0/Symfony/Component/HttpFoundation/Request.html
     */
    public function __construct(\Symfony\Component\HttpFoundation\Request $request = NULL) {
        if($request) {
            $this->filters = $this->pullFilterJsonFromRequest($request);
        }
    }

    /**
     * Pulls filters from $_GET or $_POST
     * @access private
     * @param \Symfony\Component\HttpFoundation\Request $request Instance of Symfony2's request object.
     * @return string
     */
    private function pullFilterJsonFromRequest(\Symfony\Component\HttpFoundation\Request $request) {
        $filterJson = '';
        $filterFromGet = $request->query->get($this->requestParam);
        $filterFromPost = $request->request->get($this->requestParam);
        if(empty($filterFromGet) === FALSE) {
            $filterJson = $filterFromGet;
        } elseif(empty($filterFromPost) === FALSE) {
            $filterJson = $filterFromPost;
        }
        return $filterJson;
    }

    /**
     * Parses the Ext Filters and stores the results in {@link $parsedFiltes}.
     * @return \TestApp\Services\Ext\ExtFilterParser
     * @throws \UnexpectedValueException If the filters being parsed are not valid
     */
    public function parse() {
        $decodedFilters = $this->decodeFilterJson($this->filters);
        foreach($decodedFilters as $filter) {
            switch($filter->type) {
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
     * Parses the Ext Filters and then converts them into WHERE clauses for the passed in QueryBuilder object.
     * @link http://www.doctrine-project.org/api/orm/2.0/doctrine/orm/querybuilder.html
     * @param \Doctrine\ORM\QueryBuilder $queryBuilder Instance of Doctrine's Query Builder Object
     * @return \Doctrine\ORM\QueryBuilder Returns QueryBuilder with WHERE clauses attached.
     */
    public function parseIntoQueryBuilder(\Doctrine\ORM\QueryBuilder $queryBuilder) {
        $this->parse();
        foreach($this->parsedFilters as $filter) {
            $queryBuilder->andWhere('p.' . $filter['expression'] . ' ' . $filter['value']);
        }
        return $queryBuilder;
    }

    /**
     * Validates Decodes the passed in JSON into an instance of StdClass using json_decode
     * @access private
     * @param string $filterJson The JSON to be decoded
     * @return StdClass
     * @throws \InvalidArgumentException If the passed in JSON is not valid
     */
    private function decodeFilterJson($filterJson) {
        $decodedFilters = array();
        if(empty($filterJson) === FALSE) {
            $decodedFilters = json_decode($filterJson);
            if($decodedFilters === NULL) {
                throw new \InvalidArgumentException(__METHOD__ . " Expects first parameter to be a valid JSON string");
            }
            if(!is_array($decodedFilters)) {
                $decodedFilters = array($decodedFilters);
            }
        }
        return $decodedFilters;
    }

    /**
     * Translates Ext's custom comparison type into the standard '>', '<', and '=' symbols
     * @access private
     * @param string $comparisonOperator The comparison operator being translated
     * @return string
     * @throws \UnexpectedValueException If the comparison operator is not one of the expected values
     */
    private function translateComparisonOperator($comparisonOperator) {
        $operator = '';
        switch($comparisonOperator) {
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
                throw new \UnexpectedValueException(__METHOD__ . " Invalid comparison operator '$comparisonOperator'");
        }
        return $operator;
    }

    /**
     * Parses a comparison filter
     * @access private
     * @param stdClass $filter The filter being parsed
     * @return array
     */
    private function parseComparisonFilter($filter) {
        $comparisonOperator = $this->translateComparisonOperator($filter->comparison);
        if(!is_numeric($filter->value)) {
            $filter->value = "'$filter->value'";
        }
        return array(
            'expression' => "$filter->field $comparisonOperator",
            'value' => $filter->value
        );
    }

    /**
     * Parses a Date Filter
     * @access private
     * @param stdClass $filter The filter being parsed
     * @return array
     */
    private function parseDateFilter($filter) {
        $value = $filter->value;
        if($value == '0000-00-00') {
            $value = '';
        }
        $timestamp = strtotime($value);
        if($timestamp !== FALSE) {
            $value = date("Y-m-d", $timestamp);
        }
        $filter->value = $value;
        return $this->parseComparisonFilter($filter);
    }

    /**
     * Parses a String Filter
     * @access private
     * @param stdClass $filter The filter being parsed
     * @return array
     */
    private function parseStringFilter($filter) {
        return array(
            'expression' => "$filter->field LIKE",
            'value' => "'%$filter->value%'"
        );
    }

    /**
     * Parses a List Filter
     * @access private
     * @param stdClass $filter The filter being parsed
     * @return array
     */
    private function parseListFilter($filter) {
        if(!is_array($filter->value)) {
            $filter->value = explode(',', $filter->value);
        }
        return array(
            'expression' => "$filter->field IN",
            'value' => "('" . implode("','", $filter->value) . "')"
        );
    }

}