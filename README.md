# ShopifyApiClient
A client to integrate with any PHP project, connect with Shopify Rest API
How to use:


At other PHP file, such as ShopifyService.php:

For example:

/**
     * @param $variantId
     * @return mixed
     * @throws ShopifyApiException
     */
    public function getVariantInfo($variantId){
        $client = new ShopifyApiClient($this->getStoreName(),$this->getToken());
        $variant = new ShopifyApiClientResourceVariant($client);
        $response = $variant->read($variantId,null,null);
        return $response["variant"];
    }
    
Then you can get response variant.


If you need more data such as inventory... you can add under ShopifyApiClient as:
private function registerResources(){
        $this->shop = new ShopifyApiClientResourceShop($this);
        $this->customer = new ShopifyApiClientResourceCustomer($this);
        $this->webhook = new ShopifyApiClientResourceWebhook($this);
        $this->priceRule = new ShopifyApiClientResourcePriceRule($this);
        $this->discountCode = new ShopifyApiClientResourceDiscountCode($this);
        $this->theme = new ShopifyApiClientResourceTheme($this);
        $this->asset = new ShopifyApiClientResourceAsset($this);
        $this->product = new ShopifyApiClientResourceProduct($this);
        $this->variant = new ShopifyApiClientResourceVariant($this);
        //$this->variant = new ShopifyApiClientResourceOrder($this); ******* If I want to add an order REST API 
  }
  
  
