//

var RiderAmazonAdminOption = function() {
    this.initialize.apply(this, arguments);
};

RiderAmazonAdminOption.prototype = {
   
    initialize : function() {
    },

  

    fadeInMessage: function(){
      jQuery(".fadeIn").fadeIn("slow");

    }


};

jQuery(document).ready(function(){
    //$(function(){
    var rideramazon = new RiderAmazonAdminOption();

  rideramazon.fadeInMessage();
  

});




