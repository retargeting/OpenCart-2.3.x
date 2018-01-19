<?php
/**
 * Retargeting Module for OpenCart 2.3.x
 *
 * admin/controller/extension/module/retargeting.php
 */

class ControllerExtensionModuleRetargeting extends Controller {

    private $error = array();

    /* ---------------------------------------------------------------------------------------------------------------------
     * INDEX
     * ---------------------------------------------------------------------------------------------------------------------
     */
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

        /* Loading... */
        $this->load->language('extension/module/retargeting');
        $this->load->model('setting/setting');
        $this->load->model('extension/event');
        $this->load->model('localisation/language');
        $this->load->model('design/layout');

        $this->document->setTitle($this->language->get('heading_title'));
        $data['languages'] = $this->model_localisation_language->getLanguages();

        /* Pull ALL layouts from the DB */
        $data['layouts'] = $this->model_design_layout->getLayouts();
        /* --- END --- */

        /* Check if the form has been submitted */
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

            $this->model_setting_setting->editSetting('retargeting', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'], 'SSL'));

        }
        /* --- END --- */


        /* Translated strings */
        $data['heading_title']  = $this->language->get('heading_title');
        $data['text_edit']      = $this->language->get('text_edit');
        $data['text_enabled']   = $this->language->get('text_enabled');
        $data['text_disabled']  = $this->language->get('text_disabled');
        $data['text_token']   = $this->language->get('text_token');
        $data['entry_status']   = $this->language->get('entry_status');
        $data['entry_apikey'] = $this->language->get('entry_apikey');
        $data['entry_token']  = $this->language->get('entry_token');
        $data['button_save']    = $this->language->get('button_save');
        $data['button_cancel']  = $this->language->get('button_cancel');
        $data['token'] =  $token = $this->request->get['token'];
        $data['route'] = $route = $this->request->get['route'];
        /* --- END --- */


        /* Populate the errors array */
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
        /* --- END --- */


        /* BREADCRUMBS */
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_module'),
            'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'], 'SSL')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/retargeting', 'token=' . $this->session->data['token'], 'SSL')
        );
        /* --- END --- */


        /* Module upper buttons */
        $data['action'] = $this->url->link('extension/module/retargeting', 'token=' . $this->session->data['token'], 'SSL');
        $data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'], 'SSL');
        /* --- END --- */


        /* Populate custom variables */
        if (isset($this->request->post['retargeting_status'])) {
            $data['retargeting_status'] = $this->request->post['retargeting_status'];
        } else {
            $data['retargeting_status'] = $this->config->get('retargeting_status');
        }
        
        if (isset($this->request->post['retargeting_apikey'])) {
            $data['retargeting_apikey'] = $this->request->post['retargeting_apikey'];
        } else {
            $data['retargeting_apikey'] = $this->config->get('retargeting_apikey');
        }
        
        if (isset($this->request->post['retargeting_token'])) {
            $data['retargeting_token'] = $this->request->post['retargeting_token'];
        } else {
            $data['retargeting_token'] = $this->config->get('retargeting_token');
        }
        
        /* 1. setEmail */
        if (isset($this->request->post['retargeting_setEmail'])) {
            $data['retargeting_setEmail'] = $this->request->post['retargeting_setEmail'];
        } else {
            $data['retargeting_setEmail'] = $this->config->get('retargeting_setEmail');
        }
        
        /* 2. addToCart */
        if (isset($this->request->post['retargeting_addToCart'])) {
            $data['retargeting_addToCart'] = $this->request->post['retargeting_addToCart'];
        } else {
            $data['retargeting_addToCart'] = $this->config->get('retargeting_addToCart');
        }

        /* 3. clickImage */
        if (isset($this->request->post['retargeting_clickImage'])) {
            $data['retargeting_clickImage'] = $this->request->post['retargeting_clickImage'];
        } else {
            $data['retargeting_clickImage'] = $this->config->get('retargeting_clickImage');
        }

        /* 4. commentOnProduct */
        if (isset($this->request->post['retargeting_commentOnProduct'])) {
            $data['retargeting_commentOnProduct'] = $this->request->post['retargeting_commentOnProduct'];
        } else {
            $data['retargeting_commentOnProduct'] = $this->config->get('retargeting_commentOnProduct');
        }

        /* 5. mouseOverPrice */
        if (isset($this->request->post['retargeting_mouseOverPrice'])) {
            $data['retargeting_mouseOverPrice'] = $this->request->post['retargeting_mouseOverPrice'];
        } else {
            $data['retargeting_mouseOverPrice'] = $this->config->get('retargeting_mouseOverPrice');
        }
        /* --- END --- */

        /* 6. mouseOverPrice */
        if (isset($this->request->post['retargeting_setVariation'])) {
            $data['retargeting_setVariation'] = $this->request->post['retargeting_setVariation'];
        } else {
            $data['retargeting_setVariation'] = $this->config->get('retargeting_setVariation');
        }
        /* --- END --- */

        /* Common admin area items */
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        /* --- END --- */

        /* Finally, OUTPUT */
        $this->response->setOutput($this->load->view('extension/module/retargeting.tpl', $data));


    } // End index() method


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

        if ($this->isHTMLModuleInstalled()) {
            $this->error['warning'] = 'HTML Extension is not installed';
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
            $this->error['warning'] = $this->language->get('error_apikey_required');
        }

        if (!isset($this->request->post['retargeting_token']) || strlen($this->request->post['retargeting_token']) < 3) {
            $this->error['warning'] = $this->language->get('error_token_required');
        }

        return !$this->error;
    }

    /*
    * @TODO: Add documentation
    */
    private function isHTMLModuleInstalled() {
        $result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "extension` WHERE `code` = 'html'");

        if ($result->num_rows) {
            $installed = true;
        } else {
            $installed = false;
        }

        return $installed;
    }

    /*
    * @TODO: Add documentation
    */
    public function ajax() {
        $token = $this->request->get['token'];
        $route = $this->request->get['route'];

        return $this->response->setOutput(json_encode(array($token, $route)));
    }
}
