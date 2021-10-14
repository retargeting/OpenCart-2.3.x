<?php

require_once 'retargetingconfigs.php';

class JS
{
    const CHECKOUT_MODULES = [
        'checkout/checkout',
        'checkout/simplecheckout',
        'checkout/ajaxquickcheckout',
        'checkout/ajaxcheckout',
        'checkout/quickcheckout',
        'checkout/onepagecheckout',
        'checkout/cart',
        'quickcheckout/cart',
        'quickcheckout/checkout'
    ];

    const ORDER_PAGES = [
        'checkout/success',
        'success'
    ];

    const ROOT_CATEGORY = [
        [
            'id' => 'Root',
            'name' => 'Root',
            'parent' => false,
            'breadcrumb' => []
        ]
    ];

    private $instanceOfThis;
    private $data = '';
    private $currentPage;
    private $currentCategory;
    private $manufacturerId;
    private $productId;
    private $wishList;

    /**
     * JS constructor.
     * @param $instanceOfThis
     * @param $page
     * @param $category
     * @param $manufacturer
     * @param $product
     * @param $wishList
     */
    public function __construct($instanceOfThis, $page, $category, $manufacturer, $product, $wishList = null)
    {
        $this->instanceOfThis = $instanceOfThis;
        $this->currentPage = $page;
        $this->currentCategory = $category;
        $this->manufacturerId = $manufacturer;
        $this->productId = $product;
        $this->wishList = $wishList;
    }

    /**
     * Get concatenated RT Javascript
     * @return string
     * @throws Exception
     */
    public function getMainJs()
    {
        $this->data = "/* --- Retargeting Tracker Functions --- */\n\n";

        $this->data .= $this->setEmail();

        $this->data .= $this->getPageJS($this->currentPage, $this->currentCategory, $this->manufacturerId, $this->productId);

        return $this->data;
    }

    /**
     * Logger function for debugging
     * @param $message
     */
    public function log($message)
    {
        $log = new Log('retargeting.log');
        $log->write($message);
    }

    /**
     * Set email
     * @return string
     * @throws Exception
     */
    public function setEmail()
    {
        if (isset($this->instanceOfThis->session->data['customer_id']) && !empty($this->instanceOfThis->session->data['customer_id']))
        {
            $fullName     = $this->instanceOfThis->customer->getFirstName() . ' ' . $this->instanceOfThis->customer->getLastName();

            try {
                $setEmail = new \RetargetingSDK\Email();

                $setEmail->setEmail($this->instanceOfThis->customer->getEmail());
                $setEmail->setName($fullName);
                $setEmail->setPhone($this->instanceOfThis->customer->getTelephone());

                $objSetEmail = $setEmail->getData();

            } catch (\RetargetingSDK\Exceptions\RTGException $e)
            {
                $this->log($e->getMessage());
            }

            $this->data .= "
                
                _ra.setEmailInfo = $objSetEmail;
         
                if (_ra.ready !== undefined) {
                    _ra.setEmail(_ra.setEmailInfo);
                }
            ";
        } else {

            $retargetingSetEmail = (new Configs($this->instanceOfThis))->getConfigs()['retargeting_setEmail'];

            $this->data .= "
                /* -- setEmail -- */
                function checkEmail(email) {
                    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,9})+$/;
                    return regex.test(email);
                };
                jQuery(document).ready(function($){
                    jQuery(\"{$retargetingSetEmail}\").blur(function(){
                        if ( checkEmail($(this).val()) ) {
                            _ra.setEmail({ 'email': $(this).val()});
                            console.log('setEmail fired!');
                        }
                    });
                });
            ";
        }

        return $this->data;
    }

    /**
     * Send category
     * @param $categories
     * @return string
     */
    public function sendCategory($categories)
    {
        if(!empty($categories))
        {
            $catDetails = [];

            foreach ($categories as $category)
            {
                $categoryDetails = $this->instanceOfThis->model_catalog_category->getCategory($category);

                if (isset($categoryDetails['status']) && $categoryDetails['status'] == 1)
                {
                    $catDetails[] = $categoryDetails;
                }
            }

            if(!empty($catDetails))
            {
                try {
                    $category = new \RetargetingSDK\Category();

                    foreach ($catDetails as $categoryDetail)
                    {
                        if(!empty($categoryDetail['parent_id']))
                        {
                            $subCategoryDetails = $this->instanceOfThis->model_catalog_category->getCategory($categoryDetail['parent_id']);

                            if($categoryDetail['category_id'] === $subCategoryDetails['category_id'] || !isset($subCategoryDetails))
                            {
                                break;
                            }

                            $breadcrumbCategory[] = [
                                'id' => $subCategoryDetails['category_id'],
                                'name' => $subCategoryDetails['name'],
                                'parent' => $subCategoryDetails['parent_id'] !== '0' ? $subCategoryDetails['parent_id'] : false
                            ];
                        }

                        $category->setId($categoryDetail['category_id']);
                        $category->setName($categoryDetail['name']);
                        $category->setParent($categoryDetail['parent_id'] !== '0' ? $categoryDetail['parent_id'] : false);
                        $category->setBreadcrumb($categoryDetail['parent_id'] !== '0' ? $breadcrumbCategory : []);
                    }

                    $objCategory = $category->getData();

                } catch (\RetargetingSDK\Exceptions\RTGException $exception)
                {
                    $this->log($exception->getMessage());
                }
            }
        }

        $this->data .= "
             /* --- sendCategory --- */
    
            _ra.sendCategoryInfo = $objCategory;
            
            if (_ra.ready !== undefined) {
                _ra.sendCategory(_ra.sendCategoryInfo);
            };
        ";

        return $this->data;
    }

    /**
     * Check if the current product is part of a brand
     * @param $manufactureId
     * @return string
     */
    public function sendBrand($manufactureId)
    {
        if (!empty($manufactureId))
        {
            $brand = $this->instanceOfThis->model_catalog_manufacturer->getManufacturer($manufactureId);

            try {
                $setBrand = new \RetargetingSDK\Brand();

                $setBrand->setId($brand['manufacturer_id']);
                $setBrand->setName($brand['name']);

                $objSetBrand = $setBrand->getData();

            } catch (\RetargetingSDK\Exceptions\RTGException $exception)
            {
                $this->log($exception->getMessage());
            }

            $this->data .= "
                    _ra.sendBrandInfo = $objSetBrand;

                    if (_ra.ready !== undefined) {
                        _ra.sendBrand(_ra.sendBrandInfo);
                    }
                ";

            return $this->data;
        }
    }

    /**
     * Get product price
     * @param $price
     * @param $taxClassId
     * @return float
     */
    public function getProductPrice($price, $taxClassId)
    {
        return $this->instanceOfThis->currency->format($this->instanceOfThis->tax->calculate(
            $price,
            $taxClassId,
            $this->instanceOfThis->config->get('config_tax')
        ), $this->instanceOfThis->session->data['currency']);
    }

    /**
     * Send product data
     * @param $product
     * @return string
     * @throws Exception
     */
    public function sendProduct($product)
    {
        $baseUrl = (new Configs($this->instanceOfThis))->getBaseUrl();

        $product = $this->instanceOfThis->model_catalog_product->getProduct($product);

        if (!empty($product))
        {
            $productSpecialPrice = isset($product['special'])
                ? $this->instanceOfThis->tax->calculate($product['special'], $product['tax_class_id'], $this->instanceOfThis->config->get('config_tax'))
                : 0;

            $productPrice = $this->instanceOfThis->tax->calculate($product['price'], $product['tax_class_id'], $this->instanceOfThis->config->get('config_tax'));

            $productUrl = $this->instanceOfThis->url->link('product/product', 'product_id=' . $product['product_id']);

            $productCategoryTree = $this->getProductCategoriesForFeed((int)$product['product_id']);

            $productInventory = $this->getProductInventory((int)$product['product_id'], $product['quantity']);

            $productAdditionalImages = $this->getProductImages((int)$product['product_id'], $baseUrl);

            try
            {

                $setupProduct = new \RetargetingSDK\Product();

                $setupProduct->setId($product['product_id']);
                $setupProduct->setName($product['name']);
                $setupProduct->setUrl($productUrl);
                $setupProduct->setImg($baseUrl . 'image/' . $product['image']);
                $setupProduct->setPrice($productPrice);
                $setupProduct->setPromo($productSpecialPrice);
                $setupProduct->setBrand(isset($product['manufacturer_id'])
                    ? \RetargetingSDK\Helpers\BrandHelper::validate([
                        'id'   => isset($product['manufacturer_id']) ? $product['manufacturer_id'] : 0,
                        'name' => isset($product['manufacturer']) ? $product['manufacturer'] : ''
                    ]) : false);
                $setupProduct->setCategory($productCategoryTree);
                $setupProduct->setInventory($productInventory);
                $setupProduct->setAdditionalImages($productAdditionalImages);

                $objProduct = $setupProduct->getData();

            }
            catch (\RetargetingSDK\Exceptions\RTGException $exception)
            {
                $this->log($exception->getMessage());
            }

            $this->data .= "
            _ra.sendProductInfo = $objProduct;
 
            if (_ra.ready !== undefined) {
                _ra.sendProduct(_ra.sendProductInfo);
            }
         ";

            $retargetingClickImage = (new Configs($this->instanceOfThis))->getConfigs()['retargeting_clickImage'];

            // clickImage
            $this->data .= "
            /* --- clickImage --- */
            
            jQuery(document).ready(function() {
 
               var retargeting_clickImage = \"{$retargetingClickImage}\";
               
               jQuery(retargeting_clickImage).click(function(){
                    _ra.clickImage({$product['product_id']});
                });
            });
        ";

            $retargetingAddToCart = (new Configs($this->instanceOfThis))->getConfigs()['retargeting_addToCart'];

            // addToCart
            $this->data .= "
            /* --- addToCart --- */

                var retargeting_addToCart = \"{$retargetingAddToCart}\";
                
                document.querySelector(retargeting_addToCart).addEventListener('click', function (e) {
                    _ra.addToCart({$product['product_id']}, " . (($product['quantity'] > 0) ? 1 : 0) . ", false, function(){console.log('addToCart fired!')});
                });
        ";

            //addToWishlistInfo
            if ($this->wishList)
            {
                $this->instanceOfThis->session->data['retargeting_wishlist_product_id'] = (isset($this->instanceOfThis->session->data['retargeting_wishlist_product_id']) && !empty($this->instanceOfThis->session->data['retargeting_wishlist_product_id']))
                    ? $this->instanceOfThis->session->data['retargeting_wishlist_product_id'] : '';

                $this->wishList = array_values($this->wishList);

                if ($this->instanceOfThis->session->data['retargeting_wishlist_product_id'] != ($this->wishList[count($this->wishList) - 1]))
                {
                    $product_id_in_wishlist = [];

                    for ($i = count($this->wishList) - 1; $i >= 0; $i--)
                    {
                        $product_id_in_wishlist = $this->wishList[$i];
                        break;
                    }

                    $addToWishlist = "
                    
                    _ra.addToWishlistInfo = {
                        'product_id': {$product_id_in_wishlist}
                    };

                    if (_ra.ready !== undefined) {
                        _ra.addToWishlist(_ra.addToWishlistInfo.product_id);
                    };
                ";

                    $this->instanceOfThis->session->data['retargeting_wishlist_product_id'] = $product_id_in_wishlist;

                    $this->data .= $addToWishlist;
                }
            }

            //commentOnProduct
            $retargetingCommentOnProduct = (new Configs($this->instanceOfThis))->getConfigs()['retargeting_commentOnProduct'];

            if (!empty($retargetingCommentOnProduct))
            {
                $this->data .= "
                /* -- commentOnProduct -- */
                jQuery(document).ready(function($){
                    if ($(\"{$retargetingCommentOnProduct}\").length > 0) {
                        $(\"{$retargetingCommentOnProduct}\").click(function(){
                            _ra.commentOnProduct({$product['product_id']}, function() {console.log('commentOnProduct FIRED')});
                        });
                    }
                });
              ";
            }
        }

        return $this->data;
    }

    /**
     * Check if visited or not
     * @return string
     */
    public function visitHelpPage()
    {
        $this->data .= "
            /* --- visitHelpPage --- */

            _ra.visitHelpPage = {'visit': true};
            
            if (_ra.ready !== undefined) {
                _ra.visitHelpPage();
            }
        ";

        return $this->data;
    }

    /**
     * Check if there are checkout ids or not
     * @return string
     */
    public function getCheckoutIds()
    {
        if ($this->instanceOfThis->cart->hasProducts() > 0)
        {
            $cartProducts = $this->instanceOfThis->cart->getProducts();

            $productsArr = [];

            foreach ($cartProducts as $product)
            {
                $productsArr[] = $product['product_id'];
            }

            $productsIDs = implode(",", $productsArr);

            $this->data .= "
                /* --- checkoutIds --- */

                _ra.checkoutIdsInfo = [
                    $productsIDs
                ];

                if (_ra.ready !== undefined) {
                    _ra.checkoutIds(_ra.checkoutIdsInfo);
                };
            ";

            return $this->data;
        }
    }


    /**
     * Save order
     * @return string
     * @throws Exception
     */
    public function saveOrder()
    {
        if (isset($this->session->data['order_id']) && empty($this->instanceOfThis->session->data['retargeting_save_order']))
        {
            $this->instanceOfThis->session->data['retargeting_save_order'] = $this->session->data['order_id'];
        }
        
        if ((isset($this->instanceOfThis->session->data['retargeting_save_order']) && !empty($this->instanceOfThis->session->data['retargeting_save_order'])))
        {
            $orderId = $this->instanceOfThis->session->data['retargeting_save_order'];
            $orderData = $this->instanceOfThis->model_checkout_order->getOrder($orderId);

            // Grab the ordered products based on order ID
            $orderProductQuery = $this->instanceOfThis->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$orderId . "'");

            $order = new \RetargetingSDK\Order();

            try {
                $order->setOrderNo($orderData['order_id']);
                $order->setLastName($orderData['lastname']);
                $order->setFirstName($orderData['firstname']);
                $order->setEmail($orderData['email']);
                $order->setPhone($orderData['telephone']);
                $order->setState($orderData['shipping_country']);
                $order->setCity($orderData['shipping_city']);
                $order->setAddress($orderData['shipping_address_1']);
                $order->setDiscountCode(isset($this->instanceOfThis->session->data['retargeting_discount_code']) ? $this->instanceOfThis->session->data['retargeting_discount_code'] : 0);
                $order->setDiscount(0);
                $order->setShipping(0);
                $order->setTotal($this->instanceOfThis->currency->format(
                    $orderData['total'],
                    $orderData['currency_code'],
                    $orderData['currency_value'],
                    false
                ));

                $objSaveOrder = $order->getData();

            } catch (\RetargetingSDK\Exceptions\RTGException $exception)
            {
                $this->log($exception->getMessage());
            }

            $this->data .= "

                _ra.saveOrderInfo = $objSaveOrder;
            ";

            $this->data .= "_ra.saveOrderProducts = [";

            for ($i = count($orderProductQuery->rows) - 1; $i >= 0; $i--)
            {
                $productPrice = $this->instanceOfThis->currency->format(
                    $orderProductQuery->rows[$i]['price'] + (isset($orderProductQuery->rows[$i]['tax']) ? $orderProductQuery->rows[$i]['tax'] : 0),
                    $orderData['currency_code'],
                    $orderData['currency_value'],
                    false
                );

                if ($i == 0)
                {
                    $this->data .= "{
                              'id': {$orderProductQuery->rows[$i]['product_id']},
                              'quantity': {$orderProductQuery->rows[$i]['quantity']},
                              'price': {$productPrice},
                              'variation_code': ''
                          }";

                    break;
                }

                $this->data .= "{
                          'id': {$orderProductQuery->rows[$i]['product_id']},
                          'quantity': {$orderProductQuery->rows[$i]['quantity']},
                          'price': {$productPrice},
                          'variation_code': ''
                      },";
            }

            $this->data .= "];";

            $this->data .= "
                if (_ra.ready !== undefined) {
                    _ra.saveOrder(_ra.saveOrderInfo, _ra.saveOrderProducts);
                }
            ";

            // REST API saveOrder
            $restApiKey = $this->instanceOfThis->config->get('retargeting_token');

            if (!empty($restApiKey))
            {
                $orderInfo = [
                    'order_no'      => $order->getOrderNo(),
                    'lastname'      => $order->getLastName(),
                    'firstname'     => $order->getFirstName(),
                    'email'         => $order->getEmail(),
                    'phone'         => $order->getPhone(),
                    'state'         => $order->getState(),
                    'city'          => $order->getCity(),
                    'address'       => $order->getAddress(),
                    'discount_code' => $order->getDiscountCode(),
                    'discount'      => $order->getDiscount(),
                    'shipping'      => $order->getShipping(),
                    'total'         => $order->getTotal()
                ];

                $orderProducts = [];

                foreach ($orderProductQuery->rows as $orderedProduct)
                {
                    $orderProducts[] = array(
                        'id'             => $orderedProduct['product_id'],
                        'quantity'       => $orderedProduct['quantity'],
                        'price'          => $orderedProduct['price'],
                        'variation_code' => $this->getProductInventory($orderedProduct['product_id'], $orderedProduct['quantity'])
                    );
                }


                try {
                    $orderClient = new \RetargetingSDK\Api\Client($restApiKey);
                    $orderClient->setResponseFormat("json");
                    $orderClient->setDecoding(false);
                    $orderClient->order->save($orderInfo, $orderProducts);
                } catch (\RetargetingSDK\Exceptions\RTGException $exception)
                {
                    $this->log($exception->getMessage());
                }

            }
        }

        return $this->data;
    }

    /**
     * Get event js type
     * @param $page
     * @param $category
     * @param $manufactureId
     * @param $productId
     * @return string
     * @throws Exception
     */
    public function getPageJS($page, $category, $manufactureId, $productId)
    {
        switch ($page)
        {
            case 'product/category':

                $this->sendCategory($category);
                break;
            case 'product/manufacturer/info':

                $this->sendBrand($manufactureId);
                break;
            case 'product/product':

                $this->sendProduct($productId);
                break;
            case 'information/information':

                $this->visitHelpPage();
                break;
            case in_array($page, self::CHECKOUT_MODULES):

                $this->getCheckoutIds();
                break;
            case in_array($page, self::ORDER_PAGES):

                $this->saveOrder();
                break;
            default:
                return $this->data;

                break;
        }
    }

    /**
     * @param $productId
     * @return array
     */
    public function getProductCategoriesForFeed($productId)
    {
        $formatCategory =  [];
        $breadcrumbCategory = [];

        $categories = $this->instanceOfThis->model_catalog_product->getCategories($productId);

        if(!empty($categories))
        {
            $catDetails = [];

            $categoryDetails = $this->instanceOfThis->model_catalog_category->getCategory($categories[0]['category_id']);

            if (isset($categoryDetails['status']) && $categoryDetails['status'] == 1)
            {
                $catDetails = $categoryDetails;
            }

            if(!empty($catDetails['parent_id']))
            {
                $subCategoryDetails = $this->instanceOfThis->model_catalog_category->getCategory($catDetails['parent_id']);

                $breadcrumbCategory[] = [
                    'id' => $subCategoryDetails['category_id'],
                    'name' => $subCategoryDetails['name'],
                    'parent' => $subCategoryDetails['parent_id'] !== '0' ? $subCategoryDetails['parent_id'] : false
                ];
            } else {
                $catDetails['parent_id'] = 0;
            } 

            if(!isset($catDetails['category_id']) || !isset($catDetails['parent_id'])){
                return $formatCategory;
            }
            $formatCategory[] = [
                'id'    => $catDetails['category_id'],
                'name'  => $catDetails['name'],
                'parent' => $catDetails['parent_id'] !== '0' ? $catDetails['parent_id'] : false,
                'breadcrumb' => $catDetails['parent_id'] !== '0' ? $breadcrumbCategory : []
            ];
        }

        return $formatCategory;
    }

    /**
     * Get product inventory
     * @param $productId
     * @param $quantity
     * @return array
     */
    public function getProductInventory($productId, $quantity)
    {
        $inventory = $this->instanceOfThis->model_catalog_product->getProductOptions($productId);

        if(!empty($inventory))
        {
            $stock = false;

            foreach($inventory as $value)
            {
                foreach ($value['product_option_value'] as $val)
                {
                    $stock[$val['name']] = $val['quantity'] > 0;
                }
            }

            $formatInventory = [
                'variations'    => true,
                'stock'         => $stock
            ];
        }
        else {
            $formatInventory = [
                'variations'    => false,
                'stock'         => isset($quantity) && $quantity > 0
            ];
        }

        return $formatInventory;
    }

    /**
     * Get all product images
     * @param $productId
     * @param $url
     * @return array
     * @throws Exception
     */
    public function getProductImages($productId, $url)
    {
        $additionalImgs = [];

        $images = $this->instanceOfThis->model_catalog_product->getProductImages($productId);

        if(!empty($images))
        {
            foreach ($images as $img)
            {
                $additionalImgs[] = \RetargetingSDK\Helpers\UrlHelper::validate($url . 'image/' . $img['image']);
            }
        }

        return $additionalImgs;
    }
}