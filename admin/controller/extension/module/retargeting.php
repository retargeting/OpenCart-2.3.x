<?php
class ControllerExtensionModuleRetargeting extends Controller {

    private $error = array();

    public static $siteURL = null;
    public static $prefix = 'retargeting_';

    private static $configLable = array(
        'status' => array(
            'label' => 'Status',
            'type' => 'select'
        ),
        'apikey'  => array(
            'label' => 'Tracking API Key',
            'type' => 'text',
            'error' => 'code'
        ),
        'token'=> array(
            'label' => 'REST API Key',
            'type' => 'text'
        ),
        'layouts'=> array(
            'label' => 'Assigned Layouts',
            'type' => 'layouts'
        ),
        'setEmail'=> array(
            'label' => 'Email input',
            'type' => 'text',
            'description' => 'Email input query selector.',
            'placeholder' => "input[type='text']"
        ),
        'addToCart'=> array(
            'label' => 'Cart Button',
            'type' => 'text',
            'description' => 'Cart button query selector.',
            'placeholder'=> "#button-cart"
        ),
        'clickImage'=> array(
            'label' => 'Product Image',
            'type' => 'text',
            'description' => 'Main product image container query selector.',
            'placeholder'=>'a.thumbnail'
        ),
        'stock'=> array(
            'label' => 'Default Stock Status',
            'type' => 'select',
            'description' => 'Default stock Status if is negative quantity like "-1"',
            'option' => array(
                0 => 'Out of Stock',
                1 => 'In Stock'
            )
        ),
        'cron' => array(
            'label' => 'Static Feed',
            'type' => 'select',
            'description' => '<b>Set "Yes" to generate static Feed every 3 Hours - {{ site_url }}/retargeting.csv</b>{{ cron }}'
        ),
        'rec_status' => array(
            'label' => 'Recommendation Engine',
            'type' => 'select',
            'description' => 'If active, please add Div from RTG down below'
        )
    );

    private static $option = array(
        0 => 'Disabled',
        1 => 'Enabled'
    );
    /* TODO: RecEngine */
    private static $def = array(
        "value" => "",
        "selector" => "#content .row",
        "place" => "after"
    );

    private static $blocks = array(
        'block_1' => array(
            'title' => 'Block 1',
            'def_rtg' => array(
                "value"=>"",
                "selector"=>"#content .row",
                "place"=>"before"
            )
        ),
        'block_2' => array(
            'title' => 'Block 2',
        ),
        'block_3' => array(
            'title' => 'Block 3'
        ),
        'block_4' => array(
            'title' => 'Block 4'
        )
    );

    private static $fields = [
        'home_page' => array(
            'title' => 'Home Page',
            'type'  => 'rec_engine'
        ),
        'category_page' => array(
            'title' => 'Category Page',
            'type'  => 'rec_engine'
        ),
        'product_page' => array(
            'title' => 'Product Page',
            'type'  => 'rec_engine'
        ),
        'shopping_cart' => array(
            'title' => 'Shopping Cart',
            'type'  => 'rec_engine'
        ),
        'thank_you_page' => array(
            'title' => 'Thank you Page',
            'type'  => 'rec_engine'
        ),
        'search_page' => array(
            'title' => 'Search Page',
            'type'  => 'rec_engine'
        ),
        'page_404' => array(
            'title' => 'Page 404',
            'type'  => 'rec_engine'
        )
    ];

    /**
     * ControllerExtensionModuleRetargeting constructor.
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
    }

    /**
     * Index method
     * @throws Exception
     */
    public function index()
    {
        $this->load->language('extension/module/retargeting');

        $this->load->model('setting/setting');
        $this->load->model('extension/event');
        $this->load->model('localisation/language');
        $this->load->model('design/layout');

        $title = $this->language->get('heading_title');
        
        $cancel = $this->url->link('extension/extension', 'token=' . $this->session->data['token']. '&type=module', 'SSL');

        $action = $this->url->link('extension/module/retargeting', 'token=' . $this->session->data['token'], 'SSL');
        

        $this->document->setTitle($title);

        $data['languages'] = $this->model_localisation_language->getLanguages();
        $data['layouts']   = $this->model_design_layout->getLayouts();

        /*
         * Pull all layouts from DataBase
         */
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('retargeting', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($action);
        }
        // https://retargeting.app/en/settings/account/tracking-keys

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $cancel
        );

        $data['breadcrumbs'][] = array(
            'text' => $title,
            'href' => $action
        );

        $data['action'] = $action;

        $data['token'] = $this->session->data['token'];

        $form = array();
        foreach (self::$configLable as $key=>$value) {
            $key = self::$prefix.$key;
            if ($key !== self::$prefix.'layouts' ) {
                $data[$key] = isset($this->request->post[$key]) ? $this->request->post[$key] : $this->config->get($key);
            }

            if (empty($data[$key]) && isset($value['placeholder'])) {
                $data[$key] = $value['placeholder'];
            }

            $err = '';

            if (isset($value['error']) && isset($this->error[$value['error']])) { $err = '<div class="text-danger">'.$this->error[$value['error']].'</div>'; }

            $desc = isset($value['description']) ? '<span class="small">'.$value['description'].'</span>' : '';
            switch ($value['type']) {
                case 'select':
                    $input = '<div class="col-sm-10">
                    <select name="'.$key.'" id="input-status" class="form-control">';
                    $option = (isset($value['option']) ? $value['option'] : self::$option);

                    foreach ($option as $key1=>$value1) {
                        $input .= '<option value="'.$key1.'"'.
                        ( $data[$key] == $key1 ? ' selected="selected"' : '' ).
                        '>'.$value1.'</option>';
                    }
                    
                    $input .= '</select>'.$desc.$err.'</div>';
                    break;
                case 'layouts':
                    $input = '<div class="col-sm-10">';
                    foreach ($data['layouts'] as $key1=>$value1) {
                        $input .= '<span class="label label-default" style="margin-left:1px" name="'.$value1['name'].'">'.$value1['name'].'</span>';
                    }
                    
                    $input .= $desc.$err.'</div>';

                break;
                default:
                    $input = '<div class="col-sm-10"><input type="text" name="'.$key.'" value="'.$data[$key].'" placeholder="'.$value['label'].'" id="input_'.$key.'" class="form-control" />'.$desc.$err.'</div>';
            }

            $form[] = '<div class="form-group">
            <label class="col-sm-2 control-label" for="input_'.$key.'">'.$value['label'].'</label>
            '.$input.'
        </div>';
        }

        $form[] = '</div>
        <div class="panel-heading">
            <h3>Recommendation Engine</h3>
        </div>
        <div class="panel-body">';

        foreach (self::$fields as $row=>$selected) {
            $key = self::$prefix.$row;

            $value = isset($this->request->post[$key]) ? $this->request->post[$key] : $this->config->get($key);
            
            $form[] = '<div class="form-group">
            <label class="col-sm-2 control-label" for="input_'.$row.'">'.$selected['title'].'</label>
            <div class="col-sm-10">';
            
            foreach (self::$blocks as $k=>$v) {
                if (empty($value[$k]['value']) && empty($value[$k]['selector'])) {
                    $def = isset($v['def_rtg']) ?
                        $v['def_rtg'] : (isset($selected['def_rtg']) ? $selected['def_rtg'] : null);
    
                    $value[$k] = $def !== null ? $def : self::$def;
                }
    
                $form[] = '<label for="'.$row.'_'.$k.'">
                <strong>'.$v['title'].'</strong>
                </label>';
                $form[] = '<br /><textarea style="min-width: 50%; height: 75px;" class="form-control"'.
                        ' id="'.$row.'_'.$k.'" name="'.$key.'['.$k.'][value]" spellcheck="false">'.
                        $value[$k]['value'].'</textarea>'."\n";
    
                $form[] = '<p><span><strong>'.
                '<a href="javascript:void(0);" onclick="document.querySelectorAll(\'#'.$row.'_advace\').forEach((e)=>{e.style.display=e.style.display===\'none\'?\'block\':\'none\';});">'.
                'Show/Hide Advance</a></strong></span></p>';
    
                $form[] = '<span id="'.$row.'_advace" style="display:none" >'.
                        '<input style="width:69.5%;display:inline;" class="form-control"'.
                        ' id="" type="text" name="'.$key.'['.$k.'][selector]" '.
                        'value="'.$value[$k]['selector'].'" />'."\n";
    
                $form[] = '<select style="width:30%;display:inline;" class="form-control" id="" name="'.$key.'['.$k.'][place]">'."\n";
    
                foreach (['before', 'after'] as $v)
                {
                    $form[] = '<option value="'.$v.'"'.($value[$k]['place'] === $v ? ' selected="selected"' : '' );
                    $form[] = '>'.$v.'</option>'."\n";  
                }
    
                $form[] = '</select></span><br />'."\n";
            }

            $form[] = '</div></div>';
        }

        /*
         * Common admin area items
         */

        $breadcrumb = array();
        

        foreach ($data['breadcrumbs'] as $key=>$value) {
            $breadcrumb[] = '<li><a href="'.$value['href'].'">'.$value['text'].'</a></li>';   
        }

        $warning = isset($this->error['warning']) ? 
        '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> '.$this->error['warning'].'
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>' : '';


        if (isset($this->session->data['success'])) {
			$success = '<div class="alert alert-success alert-dismissible"><i class="fa fa-check-circle"></i> '.$this->session->data['success'].'
            <button type="button" class="close" data-dismiss="alert">&times;</button></div>';

			unset($this->session->data['success']);
		} else {
			$success = '';
		}
        
        /* <h1>'.$title.'</h1> */
        $html = $this->load->controller('common/header').$this->load->controller('common/column_left').
        '<div id="content">
            <div class="page-header">
                <div class="container-fluid">
                    <div class="pull-right">
                        <button type="submit" form="form-retargeting" data-toggle="tooltip" title="'.$this->language->get('button_save').'" class="btn btn-primary"><i class="fa fa-save"></i></button>
                        <a href="'.$cancel.'" data-toggle="tooltip" title="'.$this->language->get('button_cancel').'" class="btn btn-default"><i class="fa fa-reply"></i></a>
                    </div>
                    <ul class="breadcrumb">
                        '.implode("\n",$breadcrumb).'
                    </ul>
                </div>
            </div>
            <div class="container-fluid">
                '.$warning.$success.'
                <div class="panel panel-default">
                    <form action="'.$action.'" method="post" enctype="multipart/form-data" id="form-retargeting" class="form-horizontal">      
                        <div class="panel-heading">
                            <img src="https://retargeting.biz/img/logos/LOGO_retargeting.svg" class="img-responsive" style="height:40px;padding:5px;" alt="Retargeting Tracker" />
                        </div>
                        <div class="panel-body">
                            <div class="alert alert-info"><i class="fa fa-info-circle"></i>
                                Login to your <a href="https://retargeting.app/en/settings/account/tracking-keys" target="_blank" rel="noopener noreferrer"><u>Retargeting.Biz</u></a>
                                account and copy paste the Tracking API and REST API keys into the fields below.
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                            </div>
                            '.implode("\n",$form).'
                        </div>
                    </form>
                </div>
            </div>
        </div>'.

        $this->load->controller('common/footer');

        if (isset($data[self::$prefix.'cron']) && $data[self::$prefix.'cron'] == 1) {
//             $dir = dirname(DIR_APPLICATION);
            $data['cron'] = "<br /><b>Please make sure you have this cronJob in your Hosting CronJob List <br />
<pre style='color:red'>0 */3 * * * curl --silent {$this->getSiteUrl()}/?csv=retargeting-cron </pre></b>";
        } else {
            $data['cron'] = "";
        }

        $this->response->setOutput(
            str_replace(
                array('{{ site_url }}', '{{ cron }}'),
                array($this->getSiteUrl(),
                $data['cron']
            ), $html)
        );
    }

    public function getSiteUrl() {
        if (self::$siteURL === null) {
            if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
                isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
                $protocol = 'https://';
            } else {
                $protocol = 'http://';
            }

            self::$siteURL = $protocol.$_SERVER['HTTP_HOST'];
        }
        return self::$siteURL;
    }

    /* ---------------------------------------------------------------------------------------------------------------------
     * INSTALL
     * ---------------------------------------------------------------------------------------------------------------------
     */
    public function install() {

        $this->load->model('extension/event'); // OpenCart 2.0.1+
        $this->load->model('design/layout');
        $this->load->model('setting/setting');

        foreach ($this->model_design_layout->getLayouts() as $layout) {

            $this->db->query("
                              INSERT INTO " . DB_PREFIX . "layout_module SET
                                layout_id = '{$layout['layout_id']}',
                                code = 'retargeting',
                                position = 'content_bottom',
                                sort_order = '99'
                            ");
        }

        // $this->model_extension_event->addEvent('retargeting', 'catalog/model/checkout/order/addOrderHistory/after', 'extension/module/retargeting/pre_order_add');
        // $this->model_extension_event->addEvent('retargeting', 'catalog/model/checkout/order/addOrderHistory/after', 'extension/module/retargeting/post_order_add');

        $this->model_extension_event->addEvent(
            'retargeting_add_order',
            'catalog/model/checkout/order/addOrderHistory/after',
            'extension/module/retargeting/eventAddOrderHistory'
            );

    }

    /* ---------------------------------------------------------------------------------------------------------------------
     * UNINSTALL
     * ---------------------------------------------------------------------------------------------------------------------
     */
    public function uninstall() {

        $this->load->model('extension/event'); // OpenCart 2.0.1+
        //$this->load->model('tool/event'); // OpenCart 2.0.0
        $this->load->model('design/layout');
        $this->load->model('setting/setting');

        $this->db->query("DELETE FROM " . DB_PREFIX . "layout_module WHERE code = 'retargeting'");
        $this->model_setting_setting->deleteSetting('retargeting');
        $this->model_extension_event->deleteEvent('retargeting');
        $this->model_extension_event->deleteEvent('retargeting_add_order');

    }

    /* ---------------------------------------------------------------------------------------------------------------------
     * VALIDATE
     * ---------------------------------------------------------------------------------------------------------------------
     */
    public function validate() {

        if (!$this->user->hasPermission('modify', 'extension/module/retargeting')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!isset($this->request->post['retargeting_apikey']) || strlen($this->request->post['retargeting_apikey']) < 3) {
            $this->error['warning'] = $this->language->get('error_apikey');
        }

        if (!isset($this->request->post['retargeting_token']) || strlen($this->request->post['retargeting_token']) < 3) {
            $this->error['warning'] = $this->language->get('error_token');
        }

        return !$this->error;
    }
}
