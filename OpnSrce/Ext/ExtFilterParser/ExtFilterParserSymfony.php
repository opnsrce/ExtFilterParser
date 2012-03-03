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

class ExtFilterParserSymfony extends \OpnSrce\Ext\ExtFilterParser\ExtFilterParser {

    /**
     * ExtFilterParser
     * @param \Symfony\Component\HttpFoundation\Request $request Instance of Symfony2's request object.
     * @link api.symfony.com/2.0/Symfony/Component/HttpFoundation/Request.html
     */
    public function __construct(\Symfony\Component\HttpFoundation\Request $request = NULL, $requestParam = "filter", $dateFormat = "Y-m-d") {
        $this->requestParam = $requestParam;
        $this->dateFormat = $dateFormat;

        if($request) {
            $this->filters = $this->pullFiltersFromGetOrPost($request);
        }
    }

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
     * @link http://www.doctrine-project.org/api/orm/2.0/doctrine/orm/querybuilder.html
     * @param \Doctrine\ORM\QueryBuilder $query_builder Instance of Doctrine's Query Builder Object
     * @return \Doctrine\ORM\QueryBuilder Returns QueryBuilder with WHERE clauses attached.
     */
    public function parseIntoQuery(\Doctrine\ORM\QueryBuilder $query_builder) {
        $this->parse();
        foreach($this->parsedFilters as $filter) {
            $query_builder->andWhere($filter['expression'] . ' ' . $filter['value']);
        }

        return $query_builder;
    }

}

?>