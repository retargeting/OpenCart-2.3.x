<?php
/**
 * Retargeting Module for OpenCart 2.3.x
 *
 * catalog/controller/extension/module/retargeting.php
 */

// require_once 'Retargeting_REST_API_Client.php';
require_once 'retargetingconfigs.php';
require_once 'retargetingjs.php';

require_once $_SERVER['DOCUMENT_ROOT'] .'/catalog/controller/extension/module/retargetingconfigs.php';
require_once $_SERVER['DOCUMENT_ROOT'] .'/catalog/controller/extension/module/retargetingjs.php';

class ControllerExtensionModuleRetargeting extends Controller {

    /**
     * Get current page
     * @return bool
     */
    public function getCurrentPage()
    {
        if(isset($this->request->get['route']))
        {
            return $this->request->get['route'];
        }

        return false;
    }

    /**
     * Get current category
     * @return string|array
     */
    public function getCurrentCategory()
    {
        if(!empty($this->request->get['path']) && is_array($this->request->get['path']))
        {
            return explode('_', $this->request->get['path']);
        }
        else if(!empty($this->request->get['path']) )
        {
            return explode('_', $this->request->get['path']);
        }

        return '';
    }

    /**
     * Get product id from request
     * @return string
     */
    public function getProductId()
    {
        return isset($this->request->get['product_id']) ? $this->request->get['product_id'] : '';
    }

    /**
     * Get manufactured id
     * @return string
     */
    public function getManufacturedId()
    {
        return isset($this->request->get['manufacturer_id']) ? $this->request->get['manufacturer_id'] : '';
    }

    public static function formatPrice($price)
    {
        if(!is_numeric($price))
        {
            self::_throwException('wrongPrice');
        }

        return floatval(round($price, 2));
    }

    /**
     * Get products feed
     * @param $start
     * @param $limit
     * @throws Exception
     */

    public function getProductsFeed($start, $limit)
    {
        header("Content-Disposition: attachment; filename=retargeting.csv");
        header("Content-type: text/csv");

        $params = [
            'start' => $start,
            'limit' => $limit
        ];

        $baseUrl = (new Configs($this))->getBaseUrl();

        $productsLoop = true;

        $outstream = fopen('php://output', 'w');

        fputcsv($outstream, [
            'product id',
            'product name',
            'product url',
            'image url',
            'stock',
            'price',
            'sale price',
            'brand',
            'category',
            'extra data'
        ], ',', '"');

        while($productsLoop) {

            $products = $this->model_catalog_product->getProducts($params);

            if(empty($products)) {
                $productsLoop = false;
                break;
            }

            foreach ($products as $key => $product) {

                $productPrice = $this->formatPrice($product['price']);
                $productSpecialPrice = $product['special'] !== null ? $this->formatPrice($product['special']) : 0;
                $productPrice = $this->tax->calculate($productPrice, $product['tax_class_id'], $this->config->get('config_tax'), false, false);
                $productPrice = round($productPrice, 2);

                if ($productSpecialPrice === 0) {
                    $productSpecialPrice = $productPrice;
                } else {
                    $productSpecialPrice = $this->tax->calculate($productSpecialPrice, $product['tax_class_id'], $this->config->get('config_tax'), false, false);
                }

                $productUrl = $this->url->link('product/product', 'product_id=' . $product['product_id']);

                $productCategoryTree = (new JS($this,
                    $this->getCurrentPage(),
                    $this->getCurrentCategory(),
                    $this->getManufacturedId(),
                    $this->getProductId()))->getProductCategoriesForFeed((int)$product['product_id']);


                if ($product['quantity'] == 0 || $product['quantity'] < 0 || $productPrice == 0 || empty($productCategoryTree[0]['name'])) {
                    continue;
                }

//                $productAdditionalImages = $this->getProductImages((int)$product['product_id'], $baseUrl);

                $extraData = $this->getExtraData([
                    'categories' => $this->model_catalog_product->getCategories($product['product_id']),
                    'product_id' => $product['product_id'],
                    'base_url'   => $baseUrl
                ]);

                if (!empty($product['image'])) {
                    $productImage = $baseUrl . 'image/' . $product['image'];
                } else if (!empty($this->config->get('config_logo'))) {
                    $productImage = $this->config->get('config_url') . 'image/' . $this->config->get('config_logo');
                } else {
                    $productImage = $this->config->get('config_url') . 'image/no_image-40x40.png';
                }

                $roundePrice = number_format($productPrice, 2, '.', '');

                $price = number_format($productPrice, 2, '.', '');
                $promoPrice = $productSpecialPrice > 0 ? number_format($productSpecialPrice, 2, '.', '') : $price;

                $options = $this->model_catalog_product->getProductOptions($product['product_id']);

                foreach($options as $optionValue) {

                    foreach ($optionValue['product_option_value'] as $option) {

                        if (empty($option['price'])) {
                            continue;
                        }

                        $extraData['variations'][] = [
                            'code' => $option['name'],
                            'price' => $option['price_prefix'] === '+' ? $price + $option['price'] : $price - $option['price'],
                            'sale_price' => $option['price_prefix'] === '+' ? $promoPrice + $option['price'] : $promoPrice - $option['price'],
                            'stock' => $option['quantity']
                        ];

                    }

                }

                $product = [
                    'product id' => $product['product_id'],
                    'product name' => str_replace('"', "'", $product['name']),
                    'product url' => htmlspecialchars_decode($productUrl),
                    'image url' => str_replace(' ', '%20', $productImage),
                    'stock' => $product['quantity'],
                    'price' => number_format($roundePrice, 2, '.', ''),
                    'sale price' => $productSpecialPrice > 0 ? number_format($productSpecialPrice, 2, '.', '') : $roundePrice,
                    'brand' => $product['manufacturer'],
                    'category' => $productCategoryTree[0]['name'],
                    'extra data' => json_encode($extraData, JSON_UNESCAPED_UNICODE)
                ];

                fputcsv($outstream, $product, ',', '"');
            }

            $params['start'] += $params['limit'];

        }
      
        fclose($outstream);
        die;

    }

    private $checkHTTP = null;

    public function fixURL($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) && !strpos($url, "%20")) {
            $new_URL = explode("?", $url, 2);
            $newURL = explode("/",$new_URL[0]);
    
            if ($this->checkHTTP === null) {
                $this->checkHTTP = !empty(array_intersect(["https:","http:"], $newURL));
            } 
            
            foreach ($newURL as $k=>$v ){
                if (!$this->checkHTTP || $this->checkHTTP && $k > 2) {
                    $newURL[$k] = rawurlencode($v);
                }
            }
    
            if (isset($new_URL[1])) {
                $new_URL[0] = implode("/",$newURL);
                $new_URL[1] = str_replace("&amp;","&",$new_URL[1]);
                return implode("?", $new_URL);
            } else {
                return implode("/",$newURL);
            }
        }
        return $url;
    }

    /**
     * @param array $params
     * @return array
     */
    private function getExtraData($params = []) {

        return [
            'margin' => null,
            'categories' => $this->refactorCategories($params['categories']),
            'media gallery' => $this->getImagesOfProduct($params['product_id'], $params['base_url']),
            'in_supplier_stock' => null,
            'variations' => []
        ];


    }

    /**
     * @param $categories
     */
    public function refactorCategories($categories) {

        $reCategories = [];
        foreach ($categories as $category) {

            $getCategory = $this->model_catalog_category->getCategory($category['category_id']);

            $reCategories[$getCategory['category_id']] = $getCategory['name'];

        }

        return $reCategories;
    }

    /**
     * @param $product_id
     * @param $base_url
     * @return array
     */
    public function getImagesOfProduct($product_id, $base_url) {

        $images = $this->model_catalog_product->getProductImages($product_id);

        $productimages = [];
        foreach ($images as $image) {
            $productimages[] = $this->fixURL($base_url . 'image/' . $image['image']);
        }

        return $productimages;

    }

    public function initProductsFeed()
    {
        if (isset($_GET))
        {
            //Products Feed
            if(isset($_GET['csv']) && $_GET['csv'] === 'retargeting')
            {
                $start = isset($_GET['start']) ? $_GET['start'] : 0;
                $limit = isset($_GET['limit']) ? $_GET['limit'] : 250;

                $this->getProductsFeed($start, $limit);
            }

            //Plugin Version
            if (isset($_GET['csv']) && $_GET['csv'] === 'version')
            {
                header('Content-Type: application/json');

                if(VERSION)
                {
                    echo json_encode([ 'data' => [
                        'version' => VERSION
                    ]], JSON_PRETTY_PRINT);

                    die();
                }
            }
        }

    }

    public function getProductImages($productId, $url)
    {
        $additionalImgs = [];

        $images = $this->model_catalog_product->getProductImages($productId);

        if(!empty($images))
        {
            foreach ($images as $img)
            {
                $additionalImgs[] = str_replace(' ', '%20',$url . 'image/' . $img['image']);
            }
        }

        return $additionalImgs;
    }

    public function index() {

        /* ---------------------------------------------------------------------------------------------------------------------
         * Setup the protocol
         * ---------------------------------------------------------------------------------------------------------------------
         */
        if (isset($this->request->server['HTTPS']) && (($this->request->server['HTTPS'] == 'on') || ($this->request->server['HTTPS'] == '1'))) {
            $data['shop_url'] = $this->config->get('config_ssl');
        } else {
            $data['shop_url'] = $this->config->get('config_url');
        }

        /* ---------------------------------------------------------------------------------------------------------------------
         * Load the module's language file
         * ---------------------------------------------------------------------------------------------------------------------
         */
        $this->language->load('extension/module/retargeting');

        /* ---------------------------------------------------------------------------------------------------------------------
         * Load models that we might need access to
         * ---------------------------------------------------------------------------------------------------------------------
         */
        $this->load->model('checkout/order');
        $this->load->model('setting/setting');
        $this->load->model('design/layout');
        $this->load->model('catalog/category');
        $this->load->model('catalog/manufacturer');
        $this->load->model('catalog/product');
        $this->load->model('catalog/information');
        // $this->load->model('marketing/coupon'); /* Available only in the admin/ area */
//         $this->load->model('total/coupon');

        /* ---------------------------------------------------------------------------------------------------------------------
         * Get the saved values from the admin area
         * ---------------------------------------------------------------------------------------------------------------------
         */
        $data['api_key_field'] = $this->config->get('retargeting_apikey');
        $data['api_secret_field'] = $this->config->get('retargeting_token');

        $data['retargeting_setEmail'] = htmlspecialchars_decode($this->config->get('retargeting_setEmail'));
        $data['retargeting_addToCart'] = htmlspecialchars_decode($this->config->get('retargeting_addToCart'));
        $data['retargeting_clickImage'] = htmlspecialchars_decode($this->config->get('retargeting_clickImage'));
        $data['retargeting_commentOnProduct'] = htmlspecialchars_decode($this->config->get('retargeting_commentOnProduct'));
        $data['retargeting_setVariation'] = htmlspecialchars_decode($this->config->get('retargeting_setVariation'));

        /**
         * --------------------------------------
         *             Products feed
         * --------------------------------------
         **/
        /* JSON Request intercepted, kill everything else and output */
        if (isset($_GET['json']) && $_GET['json'] === 'retargeting') {

            $start = isset($_GET['start']) ? $_GET['start'] : 0;
            $limit = isset($_GET['limit']) ? $_GET['limit'] : 250;

            $this->getProductsFeed($start, $limit);

            die();
        }
        /* --- END PRODUCTS FEED  --- */
        /**
         * --------------------------------------
         *             Products feed
         * --------------------------------------
         **/
        /* JSON Request intercepted, kill everything else and output */
        if (isset($_GET['csv']) && $_GET['csv'] === 'retargeting') {
            $this->getProductsFeed();
            die();
        }
        /* --- END PRODUCTS FEED  --- */



        /**
         * ---------------------------------------------------------------------------------------------------------------------
         *
         * API poach && Discount codes generator
         *
         * ---------------------------------------------------------------------------------------------------------------------
         *
         *
         * ********
         * REQUEST:
         * ********
         * POST : key​=your_retargeting_key
         * GET : type​=0​&value​=30​&count​=3
         * * type => (Integer) 0​: Fixed; 1​: Percentage; 2​: Free Delivery;
         * * value => (Float) actual value of discount
         * * count => (Integer) number of discounts codes to be generated
         *
         *
         * *********
         * RESPONDS:
         * *********
         * json with the discount codes
         * * ['code1', 'code2', ... 'codeN']
         *
         *
         * STEP 1: check $_POST
         * STEP 2: add the discount codes to the local database
         * STEP 3: expose the codes to Retargeting
         * STEP 4: kill the script
         */
        if (isset($_GET) && isset($_GET['key']) && ($_GET['key'] === $data['api_key_field'])) {

            /* -------------------------------------------------------------
             * STEP 1: check $_POST and validate the API Key
             * -------------------------------------------------------------
             */

            /*
            include_once 'Retargeting_REST_API_Client.php';
            $client = new Retargeting_REST_API_Client($data['api_key_field'], $data['api_secret_field']);
            $client->setResponseFormat("json");
            $client->setDecoding(false);
            $client->setApiVersion('1.0');
            $client->setApiUri('https://retargeting.ro/api');
            */

            /* Check and adjust the incoming values */
            $discount_type = (isset($_GET['type'])) ? (filter_var($_GET['type'], FILTER_SANITIZE_NUMBER_INT)) : 'Received other than int';
            $discount_value = (isset($_GET['value'])) ? (filter_var($_GET['value'], FILTER_SANITIZE_NUMBER_FLOAT)) : 'Received other than float';
            $discount_codes = (isset($_GET['count'])) ? (filter_var($_GET['count'], FILTER_SANITIZE_NUMBER_INT)) : 'Received other than int';

            /* -------------------------------------------------------------
             * STEP 2: Generate and add to local database the discount codes
             * -------------------------------------------------------------
             */
            $generate_code = function() {
                return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 1) . substr(str_shuffle('AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz'), 0, 9);
            };

            $datetime = new DateTime();
            $start_date = $datetime->format('Y-m-d');
            $datetime->modify('+6 months');
            $expiration_date = $datetime->format('Y-m-d');

            for ($i = $discount_codes; $i > 0; $i--) {

                $code = $generate_code();
                $discount_codes_collection[] = $code;

                /* Discount type: Fixed value */
                if ($discount_type == 0) {

                    $this->db->query("
                                INSERT INTO " . DB_PREFIX . "coupon
                                SET name = 'Discount Code: RTG_FX',
                                    code = '{$code}',
                                    discount = '{$discount_value}',
                                    type = 'F',
                                    total = '0',
                                    logged = '0',
                                    shipping = '0',
                                    date_start = '{$start_date}',
                                    date_end = '',
                                    uses_total = '1',
                                    uses_customer = '1',
                                    status = '1',
                                    date_added = NOW()
                                ");

                    /* Discount type: Percentage */
                } elseif ($discount_type == 1) {

                    $this->db->query("
                                INSERT INTO " . DB_PREFIX . "coupon
                                SET name = 'Discount Code: RTG_PRCNT',
                                    code = '{$code}',
                                    discount = '{$discount_value}',
                                    type = 'P',
                                    total = '0',
                                    logged = '0',
                                    shipping = '0',
                                    date_start = '{$start_date}',
                                    date_end = '',
                                    uses_total = '1',
                                    uses_customer = '1',
                                    status = '1',
                                    date_added = NOW()
                                ");

                    /* Discount type: Free delivery */
                } elseif ($discount_type == 2) {

                    $this->db->query("
                                INSERT INTO " . DB_PREFIX . "coupon
                                SET name = 'Discount Code: RTG_SHIP',
                                    code = '{$code}',
                                    discount = '0',
                                    type = 'F',
                                    total = '0',
                                    logged = '0',
                                    shipping = '1',
                                    date_start = '{$start_date}',
                                    date_end = '',
                                    uses_total = '1',
                                    uses_customer = '1',
                                    status = '1',
                                    date_added = NOW()
                                ");
                }

            } // End generating discount codes


            /* -------------------------------------------------------------
             * STEP 3: Return the newly generated codes
             * -------------------------------------------------------------
             */
            if (isset($discount_codes_collection) && !empty($discount_codes_collection)) {

                /* Modify the header */
                header('Content-Type: application/json');

                /* Output the json */
                echo json_encode($discount_codes_collection);

            }


            /* -------------------------------------------------------------
             * STEP 4: Kill the script
             * -------------------------------------------------------------
             */
            die();

        } // End $_GET processing
        /* --- END API URL & DISCOUNT CODES GENERATOR  --- */



        /* ---------------------------------------------------------------------------------------------------------------------
         *
         * Start implementing Retargeting JS functions
         *
         * --------------------------------------------------------------------------------------------------------------------*/

        /* Small helpers [pre-data processing] */
        $data['cart_products'] = isset($this->session->data['cart']) ? $this->session->data['cart'] : false;
        $data['wishlist'] = !empty($this->session->data['wishlist']) ? $this->session->data['wishlist'] : false;
        $data['current_page'] = isset($this->request->get['route']) ? $this->request->get['route'] : false;
        $data['current_category'] = isset($this->request->get['path']) ? explode('_', $this->request->get['path']) : '';
        $data['count_categories'] = isset($data['current_category']) ? count($data['current_category']) : 0;
        $data['js_output'] = "/* --- START Retargeting --- */\n\n";
        /* --- END pre-data processing  --- */



        /*
         * setEmail
         */
        /* User is logged in, pull data from DB */
        if (isset($this->session->data['customer_id']) && !empty($this->session->data['customer_id'])) {
            $full_name = $this->customer->getFirstName() . $this->customer->getLastName();
            $email_address = $this->customer->getEmail();
            $phone_number = $this->customer->getTelephone();

            $data['js_output'] .= "
                                        _ra.setEmailInfo = {
                                            'email': '{$email_address}',
                                            'name': '{$full_name}',
                                            'phone': '{$phone_number}'
                                        };
                                        
                                        if (_ra.ready !== undefined) {
                                            _ra.setEmail(_ra.setEmailInfo)
                                        }
                                    ";
        } else {
            /* Listen on entire site for input data & validate it */
            $data['setEmail'] = "
                                        /* -- setEmail -- */
                                        function checkEmail(email) {
                                            var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,9})+$/;
                                            return regex.test(email);
                                        };

                                        jQuery(document).ready(function($){
                                            jQuery(\"{$data['retargeting_setEmail']}\").blur(function(){
                                                if ( checkEmail($(this).val()) ) {
                                                    _ra.setEmail({ 'email': $(this).val()});
                                                    console.log('setEmail fired!');
                                                }
                                            });
                                        });
                                        ";

            $data['js_output'] .= $data['setEmail'];
        }
        /* --- END setEmail  --- */



        /*
         * sendCategory ✓
         */
        if ($data['current_page'] === 'product/category') {

            $category_id_parent = $data['current_category'][0];
            $category_info_parent = $this->model_catalog_category->getCategory($category_id_parent);

            $data['sendCategory'] = '
                        /* -- sendCategory -- */
                                            ';
            $data['sendCategory'] = '';
            $data['sendCategory'] .= '_ra.sendCategoryInfo = {';

            /* We have a nested category */
            if (count($data['current_category']) > 1) {

                for ($i = count($data['current_category']) - 1; $i > 0; $i--) {
                    $category_id = $data['current_category'][$i];
                    $category_info = $this->model_catalog_category->getCategory($category_id);
                    $encoded_category_info_name = htmlspecialchars($category_info['name']);
                    $data['sendCategory'] .= "
                            'id': {$category_id},
                            'name': '{$encoded_category_info_name}',
                            'parent': {$category_id_parent},
                            'breadcrumb': [
                            ";
                    break;
                }

                array_pop($data['current_category']);

                for ($i = count($data['current_category']) - 1; $i >= 0; $i--) {
                    $category_id = $data['current_category'][$i];
                    $category_info = $this->model_catalog_category->getCategory($category_id);

                    if ($i === 0) {

                        $data['sendCategory'] .= "{
                                                        'id': {$category_id_parent},
                                                        'name': 'Root',
                                                        'parent': false
                                                        }
                                                        ";
                        break;
                    }

                    $data['sendCategory'] .= "{
                                                    'id': {$category_id},
                                                    'name': '{$encoded_category_info_name}',
                                                    'parent': {$category_id_parent}
                                                    },
                                                    ";
                }

                $data['sendCategory'] .= "]";

                /* We have a single category */
            } else {

                $data['category_id'] = $data['current_category'][0];
                $data['category_info'] = $this->model_catalog_category->getCategory($data['category_id']);
                $encoded_data_category_info_name = htmlspecialchars($data['category_info']['name']);
                $data['sendCategory'] .= "
                                                'id': {$data['category_id']},
                                                'name': '{$encoded_data_category_info_name}',
                                                'parent': false,
                                                'breadcrumb': []
                                                ";
            }

            //reset($data['current_category']);

            $data['sendCategory'] .= '};';
            $data['sendCategory'] .= "
                                            if (_ra.ready !== undefined) {
                                                _ra.sendCategory(_ra.sendCategoryInfo);
                                            };
                                            ";

            /* Send to output */
            $data['js_output'] .= $data['sendCategory'];
        }
        /* --- END sendCategory  --- */



        /*
         * sendBrand ✓
         */
        if ($data['current_page'] === 'product/manufacturer/info') {

            /* Check if the current product is part of a brand */
            if (isset($this->request->get['manufacturer_id']) && !empty($this->request->get['manufacturer_id'])) {
                $data['brand_id'] = $this->request->get['manufacturer_id'];
                $data['brand_name'] = $this->model_catalog_manufacturer->getManufacturer($this->request->get['manufacturer_id']);
                $encoded_data_brand_name = htmlspecialchars($data['brand_name']['name']);
                $data['sendBrand'] = "
                                            _ra.sendBrandInfo = {
                                                                'id': {$data['brand_id']},
                                                                'name': '{$encoded_data_brand_name}'
                                                                };

                                                                if (_ra.ready !== undefined) {
                                                                    _ra.sendBrand(_ra.sendBrandInfo);
                                                                };
                                            ";

                /* Send to output */
                $data['js_output'] .= $data['sendBrand'];
            }
        }
        /* --- END sendBrand  --- */



        /*
          * sendProduct
          * likeFacebook
          */
        if ($data['current_page'] === 'product/product') {
            $product_id = $this->request->get['product_id'];
            $product_url = $this->url->link('product/product', 'product_id=' . $product_id);
            $product_details = $this->model_catalog_product->getProduct($product_id);
            $product_categories = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");
            $product_categories = $product_categories->rows; // Get all the subcategories for this product. Reorder its numerical indexes to ease the breadcrumb logic
            $decoded_product_details_name = htmlspecialchars_decode($product_details['name']);
            $decoded_product_url = htmlspecialchars_decode($product_url);
            $rootCat = array(array(
                'id' => 'Root',
                'name' => 'Root',
                'parent' => false,
                'breadcrumb' => array()

             $data['sendProduct'] = "
                                     _ra.sendProductInfo = {
                                     ";
            $data['sendProduct'] .= "
                                     'id': $product_id,
                                     'name': '{$decoded_product_details_name}',
                                     'url': '{$decoded_product_url}',
                                     'img': '{$data['shop_url']}image/{$product_details['image']}',
                                     'price': '".round(
                    $this->tax->calculate(
                        $product_details['price'],
                        $product_details['tax_class_id'],
                        $this->config->get('config_tax')
                    ),2)."',
                                     'promo': '". (
                isset(
                    $product_details['special']) ? round(
                    $this->tax->calculate(
                        $product_details['special'],
                        $product_details['tax_class_id'],
                        $this->config->get('config_tax')
                    ),2) : 0) ."',
                                     'inventory': {
                                         'variations': false,
                                         'stock' : ".(($product_details['quantity'] > 0) ? 1 : 0)."
                                     },
                                     ";
            /* Check if the product has a brand assigned */
            if (isset($product_details['manufacturer_id'])) {

                $encoded_product_details_manufacturer = htmlspecialchars($product_details['manufacturer']);
                $data['sendProduct'] .= "
                                         'brand': {'id': {$product_details['manufacturer_id']}, 'name': '{$encoded_product_details_manufacturer}'},
                                         ";
            } else {
                $data['sendProduct'] .= "
                                         'brand': false,
                                         ";
            }
            /* Check if the product has a category assigned */
            if (isset($product_categories) && !empty($product_categories)) {

                $product_cat = $this->model_catalog_product->getCategories($product_id);

                $catDetails = array();
                foreach ($product_cat as $pcatid) {
                    $categoryDetails = $this->model_catalog_category->getCategory($pcatid['category_id']);

                    if(isset($categoryDetails['status']) && $categoryDetails['status'] == 1) {
                        $catDetails[] = $categoryDetails;
                    }
                }

                $preCat = array();
                foreach ($catDetails as $productCategory) {
                    if (isset($productCategory['parent_id']) && ($productCategory['parent_id'] == 0)) {
                        $preCat = array(array(
                            'id' => $productCategory['category_id'],
                            'name' => htmlspecialchars($productCategory['name']),
                            'parent' => false,
                            'breadcrumb' => array()
                        ));

                    } else {

                        $breadcrumbDetails =  $this->model_catalog_category->getCategory($productCategory['parent_id']);
                        $preCat = array(array(
                            'id' => (int)$productCategory['category_id'],
                            'name' => htmlspecialchars($productCategory['name']),
                            'parent' => 'Root',
                            // 'parent' => (int)$productCategory['parent_id'],
                            'breadcrumb' => array(array(
                                'id' => 'Root',
                                'name' => 'Root',
                                'parent' => false
                            ))
                        ));

                    }
                }
                if ( !empty($preCat) ) {
                    $data['sendProduct'] .= "'" . 'category' . "':" . json_encode($preCat);
                } else {
                    $data['sendProduct'] .= "'" . 'category' . "':" . json_encode($rootCat);
                }

            } else {

                $data['sendProduct'] .= "'" . 'category' . "':" . json_encode($rootCat);

            }// Close check if product has categories assigned

            $data['sendProduct'] .= "};"; // Close _ra.sendProductInfo
            $data['sendProduct'] .= "
                                             if (_ra.ready !== undefined) {
                                                 _ra.sendProduct(_ra.sendProductInfo);
                                             };
                                             ";
            $data['js_output'] .= $data['sendProduct'];
            $data['likeFacebook'] = "
                                         if (typeof FB != 'undefined') {
                                             FB.Event.subscribe('edge.create', function () {
                                                 _ra.likeFacebook({$product_id});
                                             });
                                         };
                                     ";
            $data['js_output'] .= $data['likeFacebook'];
        }
        /* --- END sendProduct  --- */

        /*
         * clickImage
         */
        if ($data['current_page'] === 'product/product') {
            $clickImage_product_id = $this->request->get['product_id'];
            $clickImage_product_info = $this->model_catalog_product->getProduct($clickImage_product_id);
            $data['clickImage'] = "
                                                /* -- clickImage -- */
                                                jQuery(document).ready(function(){
                                                        /* -- clickImage -- */
                                                        jQuery(\"{$data['retargeting_clickImage']}\").click(function(){
                                                            _ra.clickImage({$clickImage_product_id});
                                                        });
                                                });
                                                ";
            $data['js_output'] .= $data['clickImage'];
        }

        /* --- END clickImage --- */

        /*
         * addToWishList ✓
         */
        if (($data['wishlist'])) {

            /* Prevent notices */
            $this->session->data['retargeting_wishlist_product_id'] = (isset($this->session->data['retargeting_wishlist_product_id']) && !empty($this->session->data['retargeting_wishlist_product_id'])) ? $this->session->data['retargeting_wishlist_product_id'] : '';

            /* While pushing out an item from the WishList with a lower array index, OpenCart won't reset the numerical indexes, thus generating a notice. This fixes it */
            $data['wishlist'] = array_values($data['wishlist']);

            if ($this->session->data['retargeting_wishlist_product_id'] != ($data['wishlist'][count($data['wishlist']) - 1])) {
                /* Get the total number of products in WishList; push the last added product into Retargeting */
                for ($i = count($data['wishlist']) - 1; $i >= 0; $i--) {
                    $product_id_in_wishlist = $data['wishlist'][$i] ;
                    break;
                }

                $data['addToWishlist'] = "
                                            _ra.addToWishlistInfo = {
                                                                    'product_id': {$product_id_in_wishlist}
                                                                    };

                                            if (_ra.ready !== undefined) {
                                                _ra.addToWishlist(_ra.addToWishlistInfo.product_id);
                                            };
                                            ";

                /* We need to send the addToWishList event one time only. */
                $this->session->data['retargeting_wishlist_product_id'] = $product_id_in_wishlist;

                $data['js_output'] .= $data['addToWishlist'];
            }
        }
        /* --- END addToWishList  --- */



        /*
         * commentOnProduct ✓
         */
        if ($data['current_page'] === 'product/product') {
            $commentOnProduct_product_info = $this->request->get['product_id'];
            $data['commentOnProduct'] = "
                                        /* -- commentOnProduct -- */
                                        jQuery(document).ready(function($){
                                            if ($(\"{$data['retargeting_commentOnProduct']}\").length > 0) {
                                                $(\"{$data['retargeting_commentOnProduct']}\").click(function(){
                                                    _ra.commentOnProduct({$commentOnProduct_product_info}, function() {console.log('commentOnProduct FIRED')});
                                                });
                                            }
                                        });
                                        ";

            $data['js_output'] .= $data['commentOnProduct'];
        }
        /* --- END commentOnProduct  --- */

        /*
                 * addToCart [v1 - class/id listener] ✓
                 */
        if ($data['current_page'] === 'product/product') {
            $mouseOverAddToCart_product_id = $this->request->get['product_id'];
            $mouseOverAddToCart_product_info = $this->model_catalog_product->getProduct($mouseOverAddToCart_product_id);
            $mouseOverAddToCart_product_promo = isset($mouseOverAddToCart_product_info['promo']) ? : 0;
            $data['mouseOverAddToCart'] = "
                /* -- addToCart -- */
                jQuery(document).ready(function($){
                    if (jQuery(\"{$data['retargeting_addToCart']}\").length > 0) {  
                        /* -- addToCart -- */
                        jQuery(\"{$data['retargeting_addToCart']}\").click(function(){
                            _ra.addToCart({$mouseOverAddToCart_product_id}, ".(($product_details['quantity'] > 0) ? 1 : 0).", false, function(){console.log('addToCart FIRED!')});
                        });
                    }
                });
            ";
            $data['js_output'] .= $data['mouseOverAddToCart'];
        }
        /* --- END mouseOverAddToCart & addToCart[v1]  --- */

        /*
         * visitHelpPage ✓
         */
        if ($data['current_page'] === 'information/information') {
            $data['visitHelpPage'] = "
                                        /* -- visitHelpPage -- */
                                        _ra.visitHelpPageInfo = {'visit' : true};
                                        if (_ra.ready !== undefined) {
                                            _ra.visitHelpPage();
                                        };
                                    ";
            $data['js_output'] .= $data['visitHelpPage'];
        }
        /* --- END visitHelpPage  --- */



        /*
         * checkoutIds ✓
         */

        $checkout_modules = array('checkout/checkout', 'checkout/simplecheckout', 'checkout/ajaxquickcheckout', 'checkout/ajaxcheckout', 'checkout/quickcheckout', 'checkout/onepagecheckout', 'checkout/cart', 'quickcheckout/cart', 'quickcheckout/checkout');
        if(in_array($data['current_page'], $checkout_modules) && $this->cart->hasProducts() > 0) {
            $cart_products = $this->cart->getProducts(); // Use this instead of session
            $data['checkoutIds'] = "
                                        /* -- checkoutIds -- */
                                        _ra.checkoutIdsInfo = [
                                    ";

            $i_products = count($cart_products);
            foreach ($cart_products as $item => $detail) {
                $i_products--;
                $data['checkoutIds'] .= ($i_products > 0) ? $detail['product_id'] . "," : $detail['product_id'];
            }

            $data['checkoutIds'] .= "
                                            ];
                                            ";
            $data['checkoutIds'] .= "
                                            if (_ra.ready !== undefined) {
                                                _ra.checkoutIds(_ra.checkoutIdsInfo);
                                            };
                                            ";

            $data['js_output'] .= $data['checkoutIds'];
        }
        /* --- END checkoutIds  --- */



        /*
         * saveOrder ✓
         * 
         * via pre.order.add event
         */

      if ($data['current_page'] === 'checkout/success') {
        if ( 
            (isset($this->session->data['retargeting_save_order']) 
            && !empty($this->session->data['retargeting_save_order'])
            )) {
            $data['order_id'] = $this->session->data['retargeting_save_order'];
            $data['order_data'] = $this->model_checkout_order->getOrder($data['order_id']);

            $order_no = $data['order_data']['order_id'];
            $lastname = $data['order_data']['lastname'];
            $firstname = $data['order_data']['firstname'];
            $email = $data['order_data']['email'];
            $phone = $data['order_data']['telephone'];
            $state = $data['order_data']['shipping_country'];
            $city = $data['order_data']['shipping_city'];
            $address = $data['order_data']['shipping_address_1'];

            $discount_code = isset($this->session->data['retargeting_discount_code']) ? $this->session->data['retargeting_discount_code'] : 0;
            $total_discount_value = 0;
            $shipping_value = 0;
            $total_order_value = $this->currency->format(
                $data['order_data']['total'],
                $data['order_data']['currency_code'],
                $data['order_data']['currency_value'],
                false
            );

            // Based on order id, grab the ordered products
            $order_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$data['order_id'] . "'");
            $data['order_product_query'] = $order_product_query;

            $data['saveOrder'] = "_ra.saveOrderInfo = {
                                            'order_no': {$order_no},
                                            'lastname': '{$lastname}',
                                            'firstname': '{$firstname}',
                                            'email': '{$email}',
                                            'phone': '{$phone}',
                                            'state': '{$state}',
                                            'city': '{$city}',
                                            'address': '{$address}',
                                            'discount_code': '{$discount_code}',
                                            'discount': {$total_discount_value},
                                            'shipping': {$shipping_value},
                                            'rebates': 0,
                                            'fees': 0,
                                            'total': {$total_order_value}
                                        };
                                        ";

                /* -------------------------------------- */
                $data['saveOrder'] .= "_ra.saveOrderProducts = [";
                for ($i = count($order_product_query->rows) - 1; $i >= 0; $i--) {
                    $product_price = $this->currency->format(
                        $order_product_query->rows[$i]['price'] + (isset($order_product_query->rows[$i]['tax']) ? $order_product_query->rows[$i]['tax'] : 0),
                        $data['order_data']['currency_code'],
                        $data['order_data']['currency_value'],
                        false
                    );
                    if ($i == 0) {
                        $data['saveOrder'] .= "{
                                                'id': {$order_product_query->rows[$i]['product_id']},
                                                'quantity': {$order_product_query->rows[$i]['quantity']},
                                                'price': {$product_price},
                                                'variation_code': ''
                                                }";
                        break;
                    }
                    $data['saveOrder'] .= "{
                                            'id': {$order_product_query->rows[$i]['product_id']},
                                            'quantity': {$order_product_query->rows[$i]['quantity']},
                                            'price': {$product_price},
                                            'variation_code': ''
                                            },";
                }
                $data['saveOrder'] .= "];";
                /* -------------------------------------- */

                $data['saveOrder'] .= "
                                        if( _ra.ready !== undefined ) {
                                            _ra.saveOrder(_ra.saveOrderInfo, _ra.saveOrderProducts);
                                        }";
                $data['js_output'] .= $data['saveOrder'];

                /*
                * REST API Save Order
                */

                $apiKey = $this->config->get('retargeting_apikey');
                $restApiKey = $this->config->get('retargeting_token');

                if($restApiKey && $restApiKey != ''){
                    $orderInfo = array(
                        'order_no' => $order_no,
                        'lastname' => $lastname,
                        'firstname' => $firstname,
                        'email' => $email,
                        'phone' => $phone,
                        'state' => $state,
                        'city' => $city,
                        'address' => $address,
                        'discount_code' => $discount_code,
                        'discount' => $total_discount_value,
                        'shipping' => $shipping_value,
                        'rebates'  =>  0,
                        'fees'     =>  0,
                        'total' => $total_order_value
                    );

                    $orderProducts = array();

                    foreach($order_product_query->rows as $orderedProduct) {
                        $orderProducts[] = array(
                            'id' => $orderedProduct['product_id'],
                            'quantity'=> $orderedProduct['quantity'],
                            'price'=> $orderedProduct['price'],
                            'variation_code'=> ''
                        );
                    }

                    $orderClient = new Retargeting_REST_API_Client($restApiKey);
                    $orderClient->setResponseFormat("json");
                    $orderClient->setDecoding(false);
                    $response = $orderClient->order->save($orderInfo,$orderProducts);
                }
/*
                $orderClient = new Retargeting_REST_API_Client($restApiKey);
                $orderClient->setResponseFormat("json");
                $orderClient->setDecoding(false);
                $response = $orderClient->order->save($orderInfo,$orderProducts);
*/

                unset($this->session->data['retargeting_save_order']);

            }
        }

        /* ---------------------------------------------------------------------------------------------------------------------
         * Set the template path for our module & load the View
         * ---------------------------------------------------------------------------------------------------------------------
         */
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . 'extension/module/retargeting.tpl')) {
            return $this->load->view($this->config->get('config_template') . 'extension/module/retargeting.tpl', $data);
        } else {
            return $this->load->view('extension/module/retargeting.tpl', $data);
        }
    }

    /* ---------------------------------------------------------------------------------------------------------------------
     * Event: post.order.add
     * 
     * Called: After the order has been launched
     * Returns: (int) $order_id
     * Used for: saveOrder js
     * ---------------------------------------------------------------------------------------------------------------------
     */

    public function eventAddOrderHistory($route, $data) {
        if(isset($data[0]) && !empty($data[0])) {
            $this->session->data['retargeting_save_order'] = (int)$data[0];
        }
    }
}
