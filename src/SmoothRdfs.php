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
 * Copyright (c) 2002-2008 (original work) 2014 Open Assessment Technologies SA
 *
 */

namespace oat\generis\altsmooth;

use oat\generis\model\data\RdfsInterface;


/**
 * Implementation of the RDFS interface for the smooth sql driver
 * 
 * @author joel bout <joel@taotesting.com>
 * @package generis
 */
class SmoothRdfs implements RdfsInterface
{
    /**
     * @var SmoothModel
     */
    private $model;
    
    public function __construct(SmoothModel $model) {
        $this->model = $model;
    }
    
    /**
     * (non-PHPdoc)
     * @see \oat\generis\model\data\RdfsInterface::getClassImplementation()
     */
    public function getClassImplementation() {
        return new Clazz($this->model);
    }
    
    /**
     * (non-PHPdoc)
     * @see \oat\generis\model\data\RdfsInterface::getResourceImplementation()
     */
    public function getResourceImplementation() {
        return new Resource($this->model);
    }
    
    /**
     * (non-PHPdoc)
     * @see \oat\generis\model\data\RdfsInterface::getPropertyImplementation()
     */
    public function getPropertyImplementation() {
        return new  Property($this->model);
    }
    
}