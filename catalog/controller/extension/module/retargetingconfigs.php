<?php

class Configs
{
    private $instanceOfThis;

    /**
     * Configs constructor.
     * @param $instanceOfThis
     */
    public function __construct($instanceOfThis)
    {
        $this->instanceOfThis = $instanceOfThis;
    }

    /**
     * Load desired models
     * @param $models
     */
    public function getModelsToLoad($models)
    {
        foreach($models as $model)
        {
            $this->instanceOfThis->load->model($model);
        }
    }

    /**
     * Get config data
     * @return mixed
     */
    public function getConfigs()
    {
        $this->instanceOfThis->load->language('extension/module/retargeting');

        $models = ['checkout/order', 'setting/setting', 'design/layout', 'catalog/category', 'catalog/manufacturer', 'catalog/product', 'catalog/information'];

        $this->getModelsToLoad($models);

        $data['api_key_field']            = $this->instanceOfThis->config->get('module_retargeting_apikey');
        $data['api_secret_field']         = $this->instanceOfThis->config->get('module_retargeting_token');
        $data['retargeting_setEmail']     = htmlspecialchars_decode($this->instanceOfThis->config->get('module_retargeting_setEmail'));
        $data['retargeting_addToCart']    = htmlspecialchars_decode($this->instanceOfThis->config->get('module_retargeting_addToCart'));
        $data['retargeting_clickImage']   = htmlspecialchars_decode($this->instanceOfThis->config->get('module_retargeting_clickImage'));
        $data['retargeting_commentOnProduct']   = htmlspecialchars_decode($this->instanceOfThis->config->get('module_retargeting_commentOnProduct'));

        return $data;
    }

    /**
     * Get shop url based on protocol
     * @return mixed
     */
    public function getBaseUrl()
    {
        if ($this->instanceOfThis->request->server['HTTPS']) {
            return $this->instanceOfThis->config->get('config_ssl');
        }

        return $this->instanceOfThis->config->get('config_url');
    }
}