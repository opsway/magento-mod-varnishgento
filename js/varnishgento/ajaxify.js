/**
 * Created by Shandy on 09.12.13.
 */

var Ajaxify;

Ajaxify = {
    params: {},
    Init: function (params) {
        this.params = params;
        this.onComplete = this.updateBlocks.bind(this);
    },
    updateAll: function () {
        var count = 0;
        var dataBlocks = {blocks: ''};
        jQuery('.ajax-loader').each(function () {
            dataBlocks['blocks'] += jQuery(this).attr('rel') + ',';
            //dataBlocks[jQuery(this).attr('id')]['name'] = jQuery(this).attr('rel');
            //dataBlocks[jQuery(this).attr('id')]['mode'] = jQuery(this).attr('mode');
            count++;
        });
        if (!count) return;
        if (this.params.messages) dataBlocks['blocks'] += 'messages,';
        dataBlocks['blocks'] = dataBlocks['blocks'].slice(0, -1);
        this.refreshBlocks(this.params.URL, dataBlocks);
    },
    updateBlocks: function (transport) {
        jQuery.each(transport.responseJSON, function (blockName, content) {
            if (blockName == 'messages') {
                jQuery('div[id="ajaxify-messages-block"]').html(content);
            }
            jQuery('div[rel="' + blockName + '"]').html(content);
        });
    },
    refreshBlocks: function (refreshUrl, params) {
        new Ajax.Request(
            refreshUrl,
            {
                method: 'get',
                parameters: params,
                onSuccess: this.onComplete
            }
        );
    },
    initMessages: function(name){
        this.params.messages = 1;
        Mage.Cookies.clear(name);
    }
};

jQuery(document).ready(function() {
    Ajaxify.updateAll();
});