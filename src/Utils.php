<?php
/**  
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * 
 * Copyright (c) 2002-2008 (original work) Public Research Centre Henri Tudor & University of Luxembourg (under the project TAO & TAO2);
 *               2008-2010 (update and modification) Deutsche Institut für Internationale Pädagogische Forschung (under the project TAO-TRANSFER);
 *               2009-2012 (update and modification) Public Research Centre Henri Tudor (under the project TAO-SUSTAIN & TAO-DEV);
 *               2012-2014 (update and modification) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 */

namespace oat\generisSmooth;

use \core_kernel_classes_Resource;
use \common_persistence_SqlPersistence;
use \core_kernel_classes_Literal;
use \common_Logger;
/**
 * Utility class for package core\kernel\persistence\smoothsql.
 * 
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 * @author Cédric Alfonsi <cerdic.alfonsi@tudor.lu>
 */
class Utils
{

    /**
     * Sort a given $dataset by language.
     *
     * @param mixed dataset A PDO dataset.
     * @param string langColname The name of the column corresponding to the language of results.
     * @return array An array representing the sorted $dataset.
     */
    static public function sortByLanguage($persistence, $dataset, $langColname)
    {
        $returnValue = array();
        
        $selectedLanguage = \common_session_SessionManager::getSession()->getDataLanguage();
        $defaultLanguage = DEFAULT_LANG;
        $fallbackLanguage = '';
        				  
        $sortedResults = array(
            $selectedLanguage => array(),
            $defaultLanguage => array(),
            $fallbackLanguage => array()
        );

        foreach ($dataset as $row) {
        	$sortedResults[$row[$langColname]][] = array(
        	    'value' => $persistence->getPlatForm()->getPhpTextValue($row['object']), 
        	    'language' => $row[$langColname]
            );
        }
        
        $returnValue = array_merge(
            $sortedResults[$selectedLanguage], 
            (count($sortedResults) > 2) ? $sortedResults[$defaultLanguage] : array(),
            $sortedResults[$fallbackLanguage]
        );
        
        return $returnValue;
    }

    /**
     * Get the first language encountered in the $values associative array.
     *
     * @param  array values
     * @return array
     */
    static public function getFirstLanguage($values)
    {
        $returnValue = array();

        if (count($values) > 0) {
            $previousLanguage = $values[0]['language'];
        
            foreach ($values as $value) {
                if ($value['language'] == $previousLanguage) {
                    $returnValue[] = $value['value'];
                } else {
                    break;
                }
            }
        }

        return (array) $returnValue;
    }

    /**
     * Filter a $dataset by language.
     *
     * @param mixed dataset
     * @param string langColname
     * @return array
     */
    static public function filterByLanguage(common_persistence_SqlPersistence $persistence, $dataset, $langColname)
    {
        $returnValue = array();
        
        $result = self::sortByLanguage($persistence, $dataset, $langColname);
        $returnValue = self::getFirstLanguage($result);
        
        return $returnValue;
    }

    /**
     * Short description of method identifyFirstLanguage
     *
     * @access public
     * @author Cédric Alfonsi, <cedric.alfonsi@tudor.lu>
     * @param  array values
     * @return string
     */
    static public function identifyFirstLanguage($values)
    {
        $returnValue = '';

        if (count($values) > 0) {
            $previousLanguage = $values[0]['language'];
            $returnValue = $previousLanguage;
            
            foreach ($values as $value) {
                if ($value['language'] == $previousLanguage) {
                    continue;
                } else {
                    $returnValue = $previousLanguage;
                    break;
                }
            }
        }

        return $returnValue;
    }

    /**
     * Build a SQL search pattern on basis of a pattern and a comparison mode.
     *
     * @param  tring pattern A value to compare.
     * @param  boolean like The manner to compare values. If set to true, the LIKE SQL operator will be used. If set to false, the = (equal) SQL operator will be used.
     * @return string
     */
    static public function buildSearchPattern(common_persistence_SqlPersistence $persistence, $pattern, $like = true)
    {
        $returnValue = '';
        
        // Take care of RDFS Literals!
        if ($pattern instanceof core_kernel_classes_Literal) {
            $pattern = $pattern->__toString();
        }
        
        switch (gettype($pattern)) {
            case 'object' :
                if ($pattern instanceof core_kernel_classes_Resource) {
                    $returnValue = '= ' . $persistence->quote($pattern->getUri());
                } else {
                    common_Logger::w('non ressource as search parameter: '. get_class($pattern), 'GENERIS');
                }
                break;
            
            default:
                if ($like === true) {
                    $like = \common_Utils::isUri($pattern) ? false : true;
                }
                $patternToken = $pattern;
                $wildcard = mb_strpos($patternToken, '*', 0, 'UTF-8') !== false;
                $object = trim(str_replace('*', '%', $patternToken));


                if ($like) {
                    if (!$wildcard && !preg_match("/^%/", $object)) {
                        $object = "%" . $object;
                    }
                    if (!$wildcard && !preg_match("/%$/", $object)) {
                        $object = $object . "%";
                    }
                    if (!$wildcard && $object === '%') {
                        $object = '%%';
                    }
                    $returnValue .= 'LIKE LOWER('. $persistence->quote($object) . ')';
                } else {
                    $returnValue .= '= '. $persistence->quote($patternToken);
                }
                break;
        }
        
        return $returnValue;
    }

    /**
     * Build where part of filter query
     * @param SmoothModel $model
     * @param array|string $class list of types (classes)
     * @return string
     */
    static public function buildWhereQuery(SmoothModel $model, $class)
    {
        $persistence = $model->getPersistence();
        $result = 'WHERE s.predicate = ' .$persistence->quote(RDF_TYPE) . PHP_EOL;

        if (is_array($class) === false) {
            $class = [$class];
        }

        $typeConditions = [];
        foreach ($class as $type) {
            $typeConditions[] = 's.object ' .self::buildSearchPattern($persistence, $type, false);
        }

        $result .= 'AND (' . implode(' OR ', $typeConditions) .  ')' . PHP_EOL;
        $result .= 'AND s.modelid IN ('.implode(',', $model->getReadableModels()).')';

        return $result;
    }

    static public function buildLanguagePattern(common_persistence_SqlPersistence $persistence, $lang = '', $tableAlias = '')
    {
        $languagePattern = '';

        if (empty($tableAlias) === false) {
            $tableAlias = $tableAlias . '.';
        }

        if (empty($lang) === false) {
            $sqlEmpty = $persistence->quote('');
            $sqlLang = $persistence->quote($lang);
            $languagePattern = "${tableAlias}l_language = ${sqlEmpty} OR ${tableAlias}l_language = ${sqlLang}";
        }
        
        return $languagePattern;
    }
    
    static public function buildFilterQuery(SmoothModel $model, $classUri, array $propertyFilters, $and = true, $like = true, $lang = '', $offset = 0, $limit = 0, $order = '', $orderDir = 'ASC')
    {
        $result = 'SELECT s.subject FROM statements s' . PHP_EOL;
        $persistence = $model->getPersistence();

        // Deal with target classes.
        if (is_array($classUri) === false) {
            $classUri = [$classUri];
        }

        $whereQuery = self::buildWhereQuery($model, $classUri);

        $filterNum = 1;
        $propertyQueries = [];
        foreach ($propertyFilters as $propertyUri => $filterValues) {
            if ($and) {
                $propertyQueries[] = self::buildFilterAnd($model, $propertyUri, $filterValues, $like, $lang, $filterNum);
            } else {
                $propertyQueries[] = self::buildFilterOr($model, $propertyUri, $filterValues, $like, $lang, $filterNum);
            }
            $filterNum++;
        }

        if ($and) {
            $filter = implode(PHP_EOL, $propertyQueries). PHP_EOL;
        } else {
            $filter = implode(' OR ' . PHP_EOL, $propertyQueries);
            $filter = "INNER JOIN (" . PHP_EOL .
                    "SELECT DISTINCT subject FROM statements" . PHP_EOL .
                    "WHERE (" . $filter . ")) filterQuery ON filterQuery.subject = s.subject". PHP_EOL;
        }

        $orderJoin = '';
        $orderStatement = '';
        if ($order) {
            $orderJoin = self::getOrderQuery($model, $order, $lang);
            $orderStatement = " ORDER BY orderq.object $orderDir";
        }


        $result = $result . $filter . $orderJoin . $whereQuery . $orderStatement;

        if ($limit > 0) {
            $result = $persistence->getPlatForm()->limitStatement($result, $limit, $offset);
        }

        return $result;
    }

    static public function buildFilterOr(SmoothModel $model, $propertyUri, $values, $like, $lang = '', $filterNum = 0)
    {
        $persistence = $model->getPersistence();

        $predicate = $persistence->quote($propertyUri);

        if (is_array($values) === false) {
            $values = [$values];
        }

        $valuePatterns = array();
        foreach ($values as $val) {
            $pattern = $like ? 'LOWER(object) ' : 'object ';
            $valuePatterns[] = $pattern . self::buildSearchPattern($persistence, $val, $like);
        }
        $sqlValues = implode(' OR ', $valuePatterns);

        $sqlLang = '';
        if (empty($lang) === false) {
            $sqlLang = ' AND (' . self::buildLanguagePattern($persistence, $lang) . ')';
        }

        $query = "(predicate=${predicate}" . PHP_EOL .
            "AND (${sqlValues}${sqlLang}))";

        return $query;
    }

    static public function buildFilterAnd(SmoothModel $model, $propertyUri, $values, $like, $lang = '', $filterNum = 0)
    {
        $persistence = $model->getPersistence();

        $predicate = $persistence->quote($propertyUri);

        if (is_array($values) === false) {
            $values = [$values];
        }

        $tableAlias = 's'.$filterNum;

        $valuePatterns = array();
        foreach ($values as $val) {
            if ($like && !\common_Utils::isUri($val)) {
                $pattern = "LOWER(${tableAlias}.object)";
            } else {
                $pattern = "${tableAlias}.object ";
            }
            $valuePatterns[] =  $pattern . self::buildSearchPattern($persistence, $val, $like);
        }

        $sqlValues = implode(' OR ', $valuePatterns);

        // Deal with language...
        $sqlLang = '';
        if (empty($lang) === false) {
            $sqlLang = ' AND (' . self::buildLanguagePattern($persistence, $lang, $tableAlias) . ')';
        }

        $query = "INNER JOIN statements ${tableAlias}" . PHP_EOL .
            "ON ${tableAlias}.subject = s.subject" . PHP_EOL .
            "AND ${tableAlias}.predicate=${predicate}" . PHP_EOL .
            "AND (${sqlValues}${sqlLang})";

        return $query;
    }

    static public function getOrderQuery(SmoothModel $model, $order, $lang = '')
    {
        $persistence = $model->getPersistence();

        $sqlLang = '';
        if (empty($lang) === false) {
            $sqlEmptyLang = $persistence->quote('');
            $sqlRequestedLang = $persistence->quote($lang);
            $sqlLang = " AND (orderq.l_language = ${sqlEmptyLang} OR orderq.l_language = ${sqlRequestedLang})";
        }

        $orderPredicate = $persistence->quote($order);

        $sqlOrderFilter = "INNER JOIN statements AS orderq" . PHP_EOL .
            "ON s.subject = orderq.subject" . PHP_EOL .
            "AND orderq.predicate = ${orderPredicate}${sqlLang}" . PHP_EOL;

        return $sqlOrderFilter;
    }
}