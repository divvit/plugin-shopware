<script type="text/javascript">

  {literal}
    !function(){var t=window.divvit=window.divvit||[];if(t.DV_VERSION="1.0.0",t.init=function(e){if(!t.bInitialized){var i=document.createElement("script");i.setAttribute("type","text/javascript"),i.setAttribute("async",!0),i.setAttribute("src","https://tag.divvit.com/tag.js?id="+e);var n=document.getElementsByTagName("script")[0];n.parentNode.insertBefore(i,n)}},!t.bInitialized){t.functions=["customer","pageview","cartAdd","cartRemove","cartUpdated","orderPlaced","nlSubscribed","dv"];for(var e=0;e<t.functions.length;e++){var i=t.functions[e];t[i]=function(e){return function(){return Array.prototype.unshift.call(arguments,e),t.push(arguments),t}}(i)}}}();
  {/literal}

  divvit.init({$merchantSiteId|@json_encode});
  divvit.pageview();

  {if $customer}

    divvit.customer({$customer|@json_encode});

  {/if}

  {if $basket}

    divvit.cartUpdated({$basket|@json_encode});

  {/if}

  {if $order}

    divvit.orderPlaced({$order|@json_encode});

  {/if}

</script>