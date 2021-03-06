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
 *
 * @category   Shopware
 * @package    Shopware_Controllers
 * @subpackage Payment
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Patrick Stahl
 * @author     $Author$
 */

/**
 * Shopware Payment Controller
 *
 * This controller handles all actions made by the user in the payment module.
 * It reads all payments, creates new ones, edits the existing payments and deletes them.
 */
class Shopware_Controllers_Backend_Payment extends Shopware_Controllers_Backend_ExtJs
{
    /**
     * @var Shopware\Models\Payment\Repository
     */
    protected $repository;

    /**
     * Entity Manager
     * @var null
     */
    protected $manager = null;

    /**
     * @var \Shopware\Models\Country\Repository
     */
    protected $countryRepository = null;

    /**
     * Internal helper function to get access to the entity manager.
     * @return null
     */
    private function getManager() {
        if ($this->manager === null) {
            $this->manager= Shopware()->Models();
        }
        return $this->manager;
    }

    /**
     * Internal helper function to get access to the country repository.
     * @return null|Shopware\Models\Country\Repository
     */
    private function getCountryRepository() {
        if ($this->countryRepository === null) {
            $this->countryRepository = Shopware()->Models()->getRepository('Shopware\Models\Country\Country');
        }
        return $this->countryRepository;
    }


    /**
     * Disable template engine for all actions
     *
     * @return void
     */
    public function preDispatch()
    {
        if (!in_array($this->Request()->getActionName(), array('index', 'load'))) {
            $this->Front()->Plugins()->Json()->setRenderer(true);
        }
    }

    public function initAcl(){
        $this->addAclPermission("getPayments", "read", "You're not allowed to see the payments.");
        $this->addAclPermission("createPayments", "create", "You're not allowed to create a payment.");
        $this->addAclPermission("updatePayments", "update", "You're not allowed to update the payment.");
        $this->addAclPermission("deletePayment", "delete", "You're not allowed to delete the payment.");
    }

    /**
     * Main-Method to get all payments and its countries and subshops
     * The data is additionally formatted, so additional-information are also given
     */
    public function getPaymentsAction()
    {
        $this->repository = Shopware()->Models()->Payment();

        $query = $this->repository->getListQuery();
        $results = $query->getArrayResult();

        $results = $this->formatResult($results);

        $this->View()->assign(array('success'=>true, 'data'=>$results));
    }

    /**
     * Helper method to
     * - set the correct icon
     * - match the surcharges to the countries
     * @param $results
     * @return mixed
     */
    private function formatResult($results)
    {
        $surchargeCollection = array();
        foreach($results as &$result){
            if($result['active']==1){
                $result['iconCls'] = 'sprite-tick-small';
            }else{
                $result['iconCls'] = 'sprite-cross-small';
            }
            $result['text'] = $result['description'].' ('.$result['id'].')';
            $result['leaf'] = true;

            //Matches the surcharges with the countries
            if(!empty($result['surchargeString'])){
                $surchargeString = $result['surchargeString'];
                $surcharges = explode(";",$surchargeString);
                $specificSurcharges = array();
                foreach($surcharges as $surcharge){
                    $specificSurcharges[] = explode(":",$surcharge);
                }
                $surchargeCollection[$result['name']] = $specificSurcharges;
            }
            if(empty($surchargeCollection[$result['name']])){
                $surchargeCollection[$result['name']] = array();
            }
            foreach($result['countries'] as &$country){
                foreach($surchargeCollection[$result['name']] as $singleSurcharge){

                    if($country['iso']==$singleSurcharge[0])
                    {
                        $country['surcharge'] = $singleSurcharge[1];
                    }
                }
            }

        }
        return $results;
    }

    /**
     * Function to get all inactive and active countries
     */
    public function getCountriesAction(){
        $result = $this->getCountryRepository()
                       ->getCountriesQuery()
                       ->getArrayResult();
        $this->View()->assign(array('success'=>true, 'data'=>$result));
    }

    /**
     * Function to create a new payment
     */
    public function createPaymentsAction() {
        try{

            $params = $this->Request()->getParams();
            unset($params["action"]);
            $repository = Shopware()->Models()->getRepository('Shopware\Models\Payment\Payment');
            $existingModel = $repository->findByName($params['name']);

            if($existingModel){
                throw new \Doctrine\ORM\ORMException('The name is already in use.');
            }
            if($params['source'] == 0){
                $params['source'] = null;
            }

            $paymentModel = new \Shopware\Models\Payment\Payment();
            $params['attribute'] = $params['attribute'][0];
            $countries = $params['countries'];
            $countryArray = array();
            foreach($countries as $country){
                $countryArray[] = Shopware()->Models()->find('Shopware\Models\Country\Country', $country['id']);
            }
            $params['countries'] = $countryArray;
            $paymentModel->fromArray($params);

            Shopware()->Models()->persist($paymentModel);
            Shopware()->Models()->flush();

            $params['id'] = $paymentModel->getId();
            $this->View()->assign(array('success'=>true, 'data'=>$params));
        } catch (\Doctrine\ORM\ORMException $e) {
            $this->View()->assign(array('success'=>false, 'errorMsg'=>$e->getMessage()));
        }
    }


    /**
     * Function to update a payment with its countries, shops and surcharges
     * The mapping for the mapping-tables is automatically created
     */
    public function updatePaymentsAction() {
        try{
            $id = $this->Request()->getParam('id', null);
            /**@var $payment \Shopware\Models\Payment\Payment  */
            $payment = Shopware()->Models()->find('Shopware\Models\Payment\Payment', $id);
			$action = $payment->getAction();
            $data = $this->Request()->getParams();
			$data['surcharge'] = str_replace(',','.',$data['surcharge']);
			$data['debitPercent'] = str_replace(',','.',$data['debitPercent']);

            $countries = new \Doctrine\Common\Collections\ArrayCollection();
            if (!empty($data['countries'])) {
                //clear all countries, to save the old and new ones then
                $payment->getCountries()->clear();
                foreach($data['countries'] as $country ) {
                    $model = Shopware()->Models()->find('Shopware\Models\Country\Country', $country['id']);
                    $countries->add($model);
                }
                $data['countries'] = $countries;
            }
	
			
            $shops = new \Doctrine\Common\Collections\ArrayCollection();
            if (!empty($data['shops'])){
                //clear all shops, to save the old and new ones then
                $payment->getShops()->clear();
                foreach($data['shops'] as $shop ) {
                    $model = Shopware()->Models()->find('Shopware\Models\Shop\Shop', $shop['id']);
                    $shops->add($model);
                }
                $data['shops'] = $shops;
            }

            $data['attribute'] = $data['attribute'][0];
			$payment->fromArray($data);

			//A default parameter "action" is sent
			//To prevent "updatePayment" written into the database
			if(empty($action)){
				$payment->setAction('');
			}else{
				$payment->setAction($action);
			}

			//ExtJS transforms null to 0
			if($payment->getSource() == 0){
				$payment->setSource(null);
			}
			if($payment->getPluginId() == 0){
				$payment->setPluginId(null);
			}

            Shopware()->Models()->persist($payment);
            Shopware()->Models()->flush();

            if($data['active']){
                $data['iconCls'] = 'sprite-tick';
            }else{
                $data['iconCls'] = 'sprite-cross';
            }

            $this->View()->assign(array('success'=>true, 'data'=>$data));

        } catch (\Doctrine\ORM\ORMException $e) {
            $this->View()->assign(array('success'=>false, 'errorMsg'=>$e->getMessage()));
        }
    }

    public function deletePaymentAction(){
        if (!$this->Request()->isPost()) {
            $this->View()->assign(array("success" => false, 'errorMsg' => 'Empty Post Request'));
            return;
        }
        $repository = Shopware()->Models()->Payment();
        $id = $this->Request()->get('id');
        /**@var $model \Shopware\Models\Payment\Payment  */
        $model = $repository->find($id);
        if($model->getSource() == 1){
            try {
                Shopware()->Models()->remove($model);
                Shopware()->Models()->flush();
                $this->View()->assign(array("success" => true));
            }
            catch (Exception $e) {
                $this->View()->assign(array("success" => false, 'errorMsg' => $e->getMessage()));
            }
        }
        else{
            $this->View()->assign(array("success" => false, 'errorMsg' => "Default payments can not be deleted"));
        }
    }
}