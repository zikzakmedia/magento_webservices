<?php

/**
 * @author Raimon Esteve
 * Inspired from Dieter's Magento Extender
 * @copyright 2009
 */

class Openlabs_OpenERPConnector_Model_Olcatalog_Product_Link extends Mage_Catalog_Model_Product_Link_Api //Mage_Core_Model_Abstract
{

    /**
     * get list product_super_link
     *
     * @param int $productId
     * @return array
     */
    public function items($productId)
    {
        $product = $this->_initProduct($productId);
        
        $childProducts=array();

        //$configurableAttributes = $product->getTypeInstance()->getConfigurableAttributesAsArray();
        
        //print_r($configurableAttributes);
        $collection = $product->getTypeInstance()->getUsedProducts();//getUsedProductCollection();

        foreach($collection as $simpleProduct)
        {
            $row = $simpleProduct->toArray();

            // naming convention
            $row["product_id"] = $row["entity_id"];
            $row["type"] = $row["type_id"];
            $row["set"] = $row["attribute_set_id"];
            $childProducts[] = $row;// $simpleProduct->toArray();
        }

        return $childProducts;

        return array();
    }

    /**
     * set product_super_link
     *
     * @param int $productId
     * @param array $linkedProductIds
     * @param array $data
     * @return array
     */
    public function assign($productId, $linkedProductIds, $data = array())
    {
        $product = $this->_initProduct($productId);
        $tmpProductIds = $product->getTypeInstance()->getUsedProductIds();
        $productIds = array();

        foreach($tmpProductIds as $key => $prodId)
        {
            $productIds[$prodId] = $prodId;
        }

        if(is_array($linkedProductIds))	{
            foreach($linkedProductIds as $prodId)
            {
                if(!key_exists($prodId,$productIds)) {$productIds[$prodId] = $prodId;}
            }
        } elseif(is_numeric($linkedProductIds))	{
            if(!key_exists($linkedProductIds,$productIds)) {$productIds[$linkedProductIds] = $linkedProductIds;}
        } else {
            return false;
        }

        $product->setConfigurableProductsData($productIds);
        $product->save();
        
        return true;
    }

    /**
     * remove product_super_link
     *
     * @param int $productId
     * @param array $linkedProductIds
     * @return array
     */
    public function remove($productId, $linkedProductIds)
    {
        $product = $this->_initProduct($productId);
        $tmpProductIds = $product->getTypeInstance()->getUsedProductIds();
        $productIds = array();

        foreach($tmpProductIds as $key => $prodId)
        {
            if(is_array($linkedProductIds))
            {
                if(!in_array($prodId, $linkedProductIds))
                {
                    
                    $productIds[$prodId] = $prodId;
                }
            } 
            elseif(is_numeric($linkedProductIds))
            {
                if($prodId!=$linkedProductIds)
                {
                    $productIds[$prodId] = $prodId;;
                }
            }
        }

        $product->setConfigurableProductsData($productIds);
        $product->save();

        return true;
    }

    private function _getStores()
    {
        $stores = Mage::getModel('core/store')
            ->getResourceCollection()
            ->setLoadDefault(true)
            ->load();
        return $stores;
    }

    /**
     * List Configurables Atributes
     *
     * @param int $productId
     * @return array
     */
    public function listSuperAttributes($productIdOrSku)
    {
        try
        {
        // check if product Exists
        $product = $this->_initProduct($productIdOrSku);
        
        if($product->getTypeId()!="configurable") $this->_fault("not a configurable product");
        
        $productId = $product->getEntityId();
        
        $stores = $this->_getStores();
        
        $superAttributes=array();
        
        foreach($stores as $store)
        {
            $product = $product = Mage::getModel('catalog/product')->setStoreId($store->getStoreId());
            $product->load($productId);
            $attrs = $product->getTypeInstance()->getConfigurableAttributesAsArray();

            foreach($attrs as $key=>$attr)
            {
                if(!isset($superAttributes[$attr["id"]]))
                {
                    $superAttributes["".$attr["id"].""] = $attr;
                    $superAttributes["".$attr["id"].""]["product_id"] = $product->getEntityId();
                    $superAttributes["".$attr["id"].""]["product_super_attribute_id"] = $attr["id"];
                    unset($superAttributes["".$attr["id"].""]["id"]);
                }

                unset($superAttributes["".$attr["id"].""]["label"]);
                
                $superAttributes["".$attr["id"].""]["labels"][$store->getStoreId()] = $attr["label"];
            }
        }
        //print_r($superAttributes);

        return array_values($superAttributes);
        } 
        catch (Exception $ex)
        {
            echo $ex;
        }
        
    }

    /**
     * Create Configurables Atributes
     *
     * @param int $productId
     * @param int $attributeID
     * @param int position
     * @param array labels
     * @param array prices
     * @return ID
     */
    public function createSuperAttribute($productIDorSku, $attributeID, $position, array $labels, array $prices = null)
    {
        $product = $this->_initProduct($productIDorSku);
        //$confAttributes = $product->getTypeInstance()->getUsedProductIds();
        
        $superAttr = Mage::getModel("catalog/product_type_configurable_attribute");
        
        $superAttr->setProductId($product->getId());
        $superAttr->setAttributeId($attributeID);
        $superAttr->setPosition($position);

        if(is_string($labels))
        {
            $superAttr->setStoreId(0);
            $superAttr->setLabel($labels);
            $superAttr->save();
        } 
        elseif(is_array($labels))
        {
            foreach($labels as $storeID => $label)
            {
                $superAttr->setStoreId($storeID);
                $superAttr->setLabel($label);
                $superAttr->save();
            }	
        }
        
        if(is_array($prices))
        {
            $superAttr->setValues($prices);
            $superAttr->save();
        }
        
        return (int)$superAttr->getId();
    }

    /**
     * Set Configurables Atributes
     *
     * @param int $product
     * @param int $attribute
     * @return True
     */
    public function setSuperAttributeValues($product, $attribute)
    {
        #get if product exists
        $product = Mage::getModel('catalog/product')->load($product)->getID();
        if(!$product){
            return False;
        }
        #get if attribute exists
        $attribute = Mage::getModel('eav/config')->getAttribute('catalog_product', 'attribute_id')->load($attribute)->getID();
        if(!$attribute){
            return False;
        }

        #try add catalog_product_super_attribute
        try {
            $resource = Mage::getSingleton('core/resource');
            $writeConnection = $resource->getConnection('core_write');
            $query = 'INSERT INTO '.$resource->getTableName('catalog_product_super_attribute').' (product_id, attribute_id) VALUES ('.$product.', '.$attribute.');';
            $writeConnection->query($query);
            return True;
        } catch (Exception $e) {
            return False;
        }
    }

    /**
     * Remove Configurables Atributes
     *
     * @param int $superAttributeID
     * @return ID
     */
    public function removeSuperAttribute($superAttributeID)
    {
        $superAttr = Mage::getModel("catalog/product_type_configurable_attribute");
        $superAttr->load($superAttributeID);
        
        $superAttr->delete();
        //$superAttr->setValues($prices);
        //$superAttr->save();
        return true;
    }
}

?>
