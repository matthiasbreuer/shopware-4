<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

class Shopware_StoreApi_Models_Query_Category extends Shopware_StoreApi_Models_Query_Query
{
    const ORDER_BY_DESCRIPTION = 'c.description',
          ORDER_BY_POSITION = 'c.position';

    protected $validOrderBy = array(
        self::ORDER_BY_DESCRIPTION,
        self::ORDER_BY_POSITION
    );

    protected $validCriterion = array(
        'Shopware_StoreApi_Models_Query_Criterion_Id',
        'Shopware_StoreApi_Models_Query_Criterion_ParentId'
    );

    /**
     * Sets the default order by value
     * @var string
     */
    protected $orderBy = self::ORDER_BY_DESCRIPTION;

    /**
     * Sets the default order direction value
     * @var string
     */
    protected $orderDirection = self::ORDER_DIRECTION_ASC;
}