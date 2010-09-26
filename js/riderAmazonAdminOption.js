//

var RiderAmazonAdminOption = function() {
    this.initialize.apply(this, arguments);
};

RiderAmazonAdminOption.prototype = {
   
    initialize : function() {
    },

  

    fadeInMessage: function(){
      jQuery(".fade").fadeIn("slow");

    }


};

jQuery(document).ready(function(){
    //$(function(){
    var riderAmazonAdminOption = new RiderAmazonAdminOption();

  riderAmazonAdminOption.fadeInMessage();
  

});




