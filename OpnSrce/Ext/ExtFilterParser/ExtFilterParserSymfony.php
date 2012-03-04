<?php

namespace OpnSrce\Ext\ExtFilterParser;

/**
 * An Extension of the ExtFilterParser class designed to work with Symfony2.
 *
 * @package Opnsrce\Ext
 * @subpackage ExtFilterParser
 * @namespace \Opnsrce\Ext\ExtFilterParser
 * @author Levi Hackwith <levi.hackwith@gmail.com>
 * @copyright 2012 Levi Hackwith
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class ExtFilterParserSymfony extends \OpnSrce\Ext\ExtFilterParser\ExtFilterParser {

    /**
     * Class constructor
     *
     * @access public
     * @param \Symfony\Component\HttpFoundation\Request $request Instance of Symfony2's Request object.
     * @param string $requestParam (Optional) The parameter inside of $_GET or _POST that the filters are pulled from. Defaults to 'filter'.
     * @param string $dateFormat (Optional) The format that date filters' values will be converted to. Defaults to 'Y-m-d'.
     * @link http://api.symfony.com/2.0/Symfony/Component/HttpFoundation/Request.html Documentation for Symfony's Request object
     */
    public function __construct(\Symfony\Component\HttpFoundation\Request $request = NULL, $request_param = "filter", $dateFormat = "Y-m-d") {
        $this->requestParam = $request_param;
        $this->dateFormat = $dateFormat;

        if($request) {
            $this->filters = $this->pullFiltersFromGetOrPost($request);
        }
    }

    /**
     * Pulls filters from $_GET or $_POST via Symfony's request object
     *
     * @access protected
     * @param \Symfony\Component\HttpFoundation\Request $request Instance of Symfony2's request object.
     * @return string
     * @link http://api.symfony.com/2.0/Symfony/Component/HttpFoundation/Request.html Documentation for Symfony's Request object
     */
    protected function pullFiltersFromGetOrPost($request) {
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
     * Parses the Ext Filters and then converts them into WHERE clauses for the passed in QueryBuilder object.
     *
     * @access public
     * @param \Doctrine\ORM\QueryBuilder $queryBuilder Instance of Doctrine's Query Builder Object
     * @link http://www.doctrine-project.org/api/orm/2.0/doctrine/orm/querybuilder.html
     * @return \Doctrine\ORM\QueryBuilder Returns QueryBuilder with WHERE clauses attached.
     */
    public function parseIntoQuery(\Doctrine\ORM\QueryBuilder $queryBuilder) {
        $this->parse();
        foreach($this->parsedFilters as $filter) {
            $query_builder->andWhere($filter['expression'] . ' ' . $filter['value']);
        }

        return $queryBuilder;
    }

}

?>