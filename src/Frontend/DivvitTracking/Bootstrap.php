<?php

class Shopware_Plugins_Frontend_DivvitTracking_Bootstrap
    extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * @const Merchant Site Id
     */
    const MERCHANT_SITE_ID = 'divvitMerchantSiteId';

    /**
     * @return array
     */
    public function getInfo()
    {
        $author    = 'Divvit AB';
        $authorUrl = 'https://www.divvit.com';

        return [
            'version'   => '1.0.2',
            'label'     => 'Divvit Tracking',
            'supplier'  => $author,
            'author'    => $author,
            'support'   => $author,
            'link'      => $authorUrl,
            'license'   => 'Commercial',
            'copyright' => sprintf('&copy; %d %s', date('Y'), $author),
        ];
    }

    /**
     * @return bool
     */
    public function install()
    {
        try {
            $form = $this->Form();

            $form->setParent(
                $this->Forms()->findOneBy(['name' => 'Interface'])
            );

            $form->setElement(
                'text',
                self::MERCHANT_SITE_ID,
                [
                    'label' => 'Frontend ID',
                    'value' => null,
                    'required' => true,
                    'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
                ]
            );

            $this->subscribeEvent(
                'Enlight_Controller_Action_PostDispatch',
                'onPostDispatch'
            );

            $this->subscribeEvent(
                'Enlight_Controller_Action_Frontend_Checkout_Cart',
                'onCheckoutCart'
            );

            $this->subscribeEvent(
                'Enlight_Controller_Action_Frontend_Checkout_AjaxDeleteArticleCart',
                'onCheckoutCart'
            );

            $this->subscribeEvent(
                'Enlight_Controller_Action_Frontend_Checkout_AjaxAddArticleCart',
                'onCheckoutCart'
            );

            $this->subscribeEvent(
                'Enlight_Controller_Action_PostDispatch_Frontend_Checkout',
                'onCheckoutFinish'
            );

            $this->Plugin()->setActive(true);

        } catch (Exception $exception) {
            $this->uninstall();
            throw $exception;
        }

        return parent::install();
    }

    /**
     * @param Enlight_Event_EventArgs $args
     * @return null
     */
    public function onPostDispatch(Enlight_Event_EventArgs $args)
    {
        if ($args->getSubject()->Request()->getModuleName() != 'frontend') {
            return null;
        }

        if (!$this->getMerchantSiteId()) {
            return null;
        }

        $view = $args->getSubject()->View();

        $view->assign('merchantSiteId', $this->getMerchantSiteId());
        $view->assign('customer', $this->getCustomerData());

        $view->addTemplateDir(sprintf('%s/Views', __DIR__));
        $view->extendsTemplate('frontend/plugins/divvit-tracking/tracking.tpl');
    }

    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onCheckoutCart(Enlight_Event_EventArgs $args)
    {
        $args->getSubject()->executeParent(sprintf('%sAction', $args->getSubject()->Request()->get('action')));
        
        $data = [
            'cartId' => Shopware()->SessionID(),
            'products' => $this->getProductsFromBasket($args->getSubject()->getBasket())
        ];

        $view = $args->getSubject()->View();
        $view->assign('basket', $data);
        
        return true;
    }

    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onCheckoutFinish(Enlight_Event_EventArgs $args)
    {
        $checkoutController = $args->getSubject();
        /* @var $checkoutController Shopware_Controllers_Frontend_Checkout */
     
        $request = $checkoutController->Request();
        if ('finish' !== $request->getActionName()) {
            return;
        }

        $order = Shopware()->Session()->offsetGet('sOrderVariables');

        $data = [
            'orderId' => $order->sOrderNumber,
            'products' => $this->getProductsFromBasket($order['sBasket']),
            'total' => number_format($order['sAmount'], 2),
            'shipping' => number_format($order['sShippingcosts'], 2),
            'paymentMethod' => $order['sPayment']['name'],
            'customer' => [
                'idFields' => $this->getCustomerData()
            ]
        ];

        $voucher = array_filter(
            $data['products'],
            function(array $product) {
                return (float)$product['price'] < 0;
            }
        );
        if ($voucher = current($voucher)) {
            $data['voucher'] = $voucher['id'];
            $data['voucherDiscount'] = $voucher['price'];
        }

        $data = ['order' => $data];

        $view = $args->getSubject()->View();
        $view->assign('order', $data);
    }

    /**
     * @return string
     */
    protected function getMerchantSiteId()
    {
        return $this->Config()->get(self::MERCHANT_SITE_ID);
    }

    /**
     * @param array $categoryTree
     * @return array
     */
    protected function computeCategoryNamesFromTree(array $categoryTree)
    {
        $categoryTree = current($categoryTree);
        $categories = [$categoryTree['name']];

        if ($subCategories = $categoryTree['subcategories']) {
            $categories = array_merge(
                $categories, 
                $this->computeCategoryNamesFromTree($subCategories)
            );
        }

        return $categories;
    }

    /**
     * @param array $basket
     * @return array
     */
    protected function getProductsFromBasket(array $basket)
    {
        $categoryModule = Shopware()->Modules()->Categories();
        $products = [];

        foreach ($basket['content'] as $product) {
            $item = [
                'id' => (string)$product['ordernumber'],
                'name' => (string)$product['articlename'],
                'price' => str_replace(',', '.', (string)$product['price']),
                'currency' => (string)$basket['sCurrencyName'],
                'quantity' => (string)$product['quantity']
            ];

            $categoryId = $categoryModule->sGetCategoryIdByArticleId($product['articleID']);
            if ($categoryTree = $categoryModule->sGetCategories($categoryId)) {
                $item['category'] = $this->computeCategoryNamesFromTree($categoryTree);
            }

            $products[] = $item;
        }

        return $products;
    }

    /**
     * @return array
     */
    protected function getCustomerData()
    {
        $customer = null;
        $userData = Shopware()->System()->sMODULES['sAdmin']->sGetUserData();
        if (!empty($userData['additional']['user']['customerId'])) {
            $customer = [
                'customerId' => $userData['additional']['user']['customerId']
            ];
        }

        return $customer;
    }
}