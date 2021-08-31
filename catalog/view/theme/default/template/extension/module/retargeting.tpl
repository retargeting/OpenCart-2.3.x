<!--
    VIEW FILE - Catalog template
    catalog/view/theme/default/template/extension/module
    retargeting.tpl

    MODULE: Retargeting
-->
<!-- START RETARGETING MODULE -->
<script>

    (function(){
    ra_key = "<?php echo $api_key_field; ?>";
    ra_params = {
        add_to_cart_button_id: 'button-cart',
        price_label_id: 'price_label_id',
    };
	var ra = document.createElement("script"); ra.type ="text/javascript"; ra.async = true;
    ra.addEventListener("load", function(event) {
        console.log("⚡ RTG loaded ⚡");
        StartRTG();
    });
    ra.src = ("https:" ==
	document.location.protocol ? "https://" : "http://") + "tracking.retargeting.biz/v3/rajs/" + ra_key + ".js";
	var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(ra,s);})();
    function StartRTG(){
        if(_ra === undefined) {
            _ra = _ra || {};
        }
<?php echo $js_output; ?>
    }
</script>
<!-- END RETARGETING MODULE 50.51.x-->
