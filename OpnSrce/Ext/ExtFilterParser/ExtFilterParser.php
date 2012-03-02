<?php

/**
 * Copyright (c) 2011 Levi Hackwith <levi.hackwith@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace OpnSrce\Ext\ExtFilterParser;

class ExtFilterParser
{

    /**
     * Stores the filters after they've been parsed with {@link parse}.
     * @var array
     */
    protected $parsedFilters = array();

    /**
     * Stores the filters passed to {@link setFilters}.
     * @var string
     */
    protected $filters = '';

    /**
     * The name of the $_GET or $_POST parameter that {@link pullFilterJsonFromRequest} looks for.
     * @var type
     */
    protected $requestParam = 'filter';

    /**
     * This is the format that the value of all date filters will be translated to
     * @var string
     */
    protected $dateFormat = 'Y-m-d';

    /**
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
     * @param string $filters
     * @return \TestApp\Services\Ext\ExtFilterParser
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * ExtFilterParser
     * @param \Symfony\Component\HttpFoundation\Request $request Instance of Symfony2's request object.
     * @link api.symfony.com/2.0/Symfony/Component/HttpFoundation/Request.html
     */
    public function __construct(\Symfony\Component\HttpFoundation\Request $request = NULL, $requestParam = "filter", $dateFormat = "Y-m-d")
    {
        $this->requestParam = $requestParam;
        $this->dateFormat = $dateFormat;

        if ($request) {
            $this->filters = $this->pullFilterJsonFromRequest($request);
        }
    }

    /**
     * Pulls filters from $_GET or $_POST
     * @access protected
     * @param \Symfony\Component\HttpFoundation\Request $request Instance of Symfony2's request object.
     * @return string
     */
    protected function pullFilterJsonFromRequest(\Symfony\Component\HttpFoundation\Request $request)
    {
        $filterJson = '';
        $filterFromGet = $request->query->get($this->requestParam);
        $filterFromPost = $request->request->get($this->requestParam);
        if (empty($filterFromGet) === FALSE) {
            $filterJson = $filterFromGet;
        }
        elseif (empty($filterFromPost) === FALSE) {
            $filterJson = $filterFromPost;
        }

        return $filterJson;
    }

    /**
     * Parses the Ext Filters and stores the results in {@link $parsedFiltes}.
     * @return \TestApp\Services\Ext\ExtFilterParser
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
     * Parses the Ext Filters and then converts them into WHERE clauses for the passed in QueryBuilder object.
     * @link http://www.doctrine-project.org/api/orm/2.0/doctrine/orm/querybuilder.html
     * @param \Doctrine\ORM\QueryBuilder $query_builder Instance of Doctrine's Query Builder Object
     * @return \Doctrine\ORM\QueryBuilder Returns QueryBuilder with WHERE clauses attached.
     */
    public function parseIntoQueryBuilder(\Doctrine\ORM\QueryBuilder $query_builder)
    {
        $this->parse();
        foreach ($this->parsedFilters as $filter) {
            $query_builder->andWhere($filter['expression'] . ' ' . $filter['value']);
        }

        return $query_builder;
    }

    /**
     * Validates Decodes the passed in JSON into an instance of StdClass using json_decode
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