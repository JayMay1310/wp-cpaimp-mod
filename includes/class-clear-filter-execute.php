<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Cpaimp_Clear_Filter_Execute' ) ) {

    class Cpaimp_Clear_Filter_Execute {

        private static $config = [];

        public function __construct($filepath) {

            self::$config = Cpaimp_Importer::get_instance();
            $xml = simplexml_load_file($filepath);
            $name_url = $xml->shop->name;

            

            if ($name_url !== null)
            {
   
                $count_product_start = $xml->shop->offers->offer->count();
            
                $count_category_start = $xml->shop->categories->category->count();

                $time_start = time();
                ImpLoggerClear()->log( 'Started clear filter at: ' . date( 'Y-m-d H:i:s', $time_start ) );
	    	    ImpLoggerClear()->log( 'Parameters: ' . print_r( self::$config, true ) );

                $category_work = $this->clearFilterCategory($filepath, $name_url);
                $time_category = time();
                ImpLoggerClear()->log( 'Filter clear category time: ' . ($time_category - $time_start) . 's' . ' Удалено товаров ' . ' ' . $category_work["DeleteProduct"]. '  Удалено категорий - ' . ' ' . $category_work["DeleteCategory"]);

                $title_work = $this->clearFilterTitle($filepath);
                $time_title = time();
                ImpLoggerClear()->log( 'Filter clear title time: ' . ($time_title - $time_category)  . 's' . ' Delete title ' . ' ' . $title_work);
              
                $vendor_work = $this->clearFilterVendor($filepath);
                $time_vendor = time();
                ImpLoggerClear()->log( 'Filter clear vendor time: ' . ($time_vendor - $time_title) . 's' . ' Delete vendor ' . ' ' . $vendor_work);

                $price_work = $this->clearFilterPrice($filepath);
                $time_price = time();
                ImpLoggerClear()->log( 'Filter clear price time: ' . ($time_price - $time_vendor) . 's' . ' Delete price ' . ' ' .  $price_work);
                
                $category_pid_work = $this->clearFilterpidCategory($filepath, $name_url);
                $time_category_pid_work = time();
                ImpLoggerClear()->log( 'Затрачено времени на фильтр Parent Filter: ' . ($time_category_pid_work - $time_price) . 's' . ' Удалено товаров - ' . ' ' . $category_pid_work["DeleteProduct"] . '  Удалено категорий - ' . ' ' . $category_pid_work["DeleteCategory"] );
                
                //следующий участок кода чистит категорий, но с ним не работают категиорий.
                //$descriptioncategory_work = $this->clearDescriptionCategory($filepath);
                //$time_descriptioncategory = time();
                //ImpLoggerClear()->log( 'Filter clear descriptioncategory time: ' . ($time_descriptioncategory - $time_price) . 's' . ' Delete description category ' . ' ' . $descriptioncategory_work);

                
                $xml = simplexml_load_file($filepath);
                $time_end = time();
            
                $count_product_fin = $xml->shop->offers->offer->count();
                $count_category_fin = $xml->shop->categories->category->count();

                ////////////////////////////////

                ImpLoggerClear()->log( 'Filter clear price time: ' . ($time_price - $time_vendor) . 's' . ' Delete price ' . ' ' .  $price_work);

                ImpLoggerClear()->log( 'Категорий для импорта до обработки - ' . $count_category_start .  '   Категорий для импорта после обработки - ' . $count_category_fin);

	    	    ImpLoggerClear()->log( 'Finished at: ' . date( 'Y-m-d H:i:s', $time_end ) . ' Товары для импорта до обработки - ' . $count_product_start . ' Товары для импорта после обработки - ' . $count_product_fin);
	    	    ImpLoggerClear()->log( 'Elapsed time: ' . ( $time_end - $time_start ) . 's' );
            }
        }

        //Фильтр категорий. 
        private function clearFilterCategory($filepath = '', $name_url)
        {
            $result_array = [];
            $del_obj_product = 0;
            $del_obj_category = 0;

            $xml = simplexml_load_file($filepath);
            $xml_buffer =simplexml_load_file($filepath);

            //$filter = $xml->xpath("//offers/offer[categoryId='1110'] | //offers/offer[categoryId='1141']");

            $category = self::$config->config('categoryidFilter');
            if ($category == '')
            {
                $result_array = ['DeleteProduct' => $del_obj_product, 'DeleteCategory' => $del_obj_category];
                return $result_array;
            }
 
            $count = 0;
            $categoryidFilter=explode(',', str_replace(array(' ',', ',';'),array(',',',',','), $category));
            foreach ($xml->shop->offers->offer as $value) {
                
                $categoryId= $value->categoryId;

                if (!in_array($categoryId, $categoryidFilter))
                {
                    unset($xml_buffer->shop->offers->offer[$count]);
                    $count--; 
                    $del_obj_product++;               
                }
                $count++;            
            }

            $count_category = 0;
            foreach ($xml->shop->categories->category as $value) {             
                $categoryId= (string)$value->attributes()->id;
                $categoryId= (int)$categoryId;

                if (!in_array($categoryId, $categoryidFilter))
                {
                    unset($xml_buffer->shop->categories->category[$count_category]);
                    $count_category--; 
                    $del_obj_category++;
                }

                $count_category++;
            }
            
            //Search parent_id   
            $res_id = $xml_buffer->xpath("//category/@parent_id"); //Возвращает массив, если нет значение возвращает нулевой массив
            if (count($res_id) !== 0)
            {
                foreach ($res_id as $node)
                {
                    unset($node[0]);
                } 
            }  
         
            //Search parentId 
            $resId = $xml_buffer->xpath("//category/@parentId");
            if (count($resId) !== 0)
            {
                foreach ($resId as $node)
                {
                    unset($node[0]);
                } 
            }

            $xml_buffer->asXML($filepath);

            $result_array = ['DeleteProduct' => $del_obj_product, 'DeleteCategory' => $del_obj_category];
            return $result_array;                                    
        }

        //Фильтр по заголовку. 
        private function clearFilterTitle($filepath = '')
        {
            $del_obj = 0;//счетчик для лога
            $xml = simplexml_load_file($filepath);
            $xml_buffer =simplexml_load_file($filepath);
       
            $titleFilter = self::$config->config('FilterTitle');

            if ($titleFilter == '')
            {
                return $del_obj;
            }
             
            $count = 0;
            foreach ($xml->shop->offers->offer as $value) {   

                $title= (string)$value->name;

			    if (stripos($title, $titleFilter) === FALSE)
                {
                    unset($xml_buffer->shop->offers->offer[$count]);
                    $count--;
                    $del_obj++;             
                }
                   
			    $count++;
            }

            $xml_buffer->asXML($filepath);
            return $del_obj;
        }

        //Фильтр по производителю.       
        private function clearFilterVendor($filepath = '')
        {
            $del_obj = 0;//счетчик для лога

            $xml = simplexml_load_file($filepath);
            $xml_buffer =simplexml_load_file($filepath);

            $filterVendor = self::$config->config('FilterVendor');

            if ($filterVendor == '')
            {
                return $del_obj;
            }            

            $count = 0;
            foreach ($xml->shop->offers->offer as $value) {   
                $vendor= $value->vendor;
			    if (strtolower($vendor) !== strtolower($filterVendor))
                {
                    unset($xml_buffer->shop->offers->offer[$count]);
                    $count--;  
                    $del_obj++;          
                }          
			    $count++;
            }

            $xml_buffer->asXML($filepath);
            return $del_obj;
        }

        //Фильтр по цене. 
        private function clearFilterPrice($filepath = '')
        {
            $del_obj = 0;//счетчик для лога
            $xml = simplexml_load_file($filepath);
            $xml_buffer =simplexml_load_file($filepath);

            $priceFilter = self::$config->config('FilterPrice');

            if ($priceFilter == '')
            {
                return $del_obj;
            }
                 
            $count = 0;
            foreach ($xml->shop->offers->offer as $value) {
                $price= $value->price;             
                //Загружать товары дороже чем....
			    if (floatval($priceFilter) < floatval($price))
                {
                    unset($xml_buffer->shop->offers->offer[$count]);
                    $count--;
                    $del_obj++;            
                }

			    $count++;
            }

            $xml_buffer->asXML($filepath);
            return $del_obj;
        }

        //Метод удалет товар, если он не отнсоится к родительской категорий. +Чистит категорий в заголовке
        private function clearFilterpidCategory($filepath = '', $name_url)
        {
            $result_array = [];
            $del_obj_product = 0;
            $del_obj_category = 0;

            $xml = simplexml_load_file($filepath);
            $xml_buffer =simplexml_load_file($filepath);

            $categorypID = self::$config->config('categorypidFilter');
            if ($categorypID == '')
            {
                $result_array = ['DeleteProduct' => $del_obj_product, 'DeleteCategory' => $del_obj_category];
                return $result_array;
            }

            $categorypidFilter=explode(',', str_replace(array(' ',', ',';'),array(',',',',','), $categorypID));

            $category_map = array();
            foreach ($xml->shop->categories->category as $value) {
                $category['id'] = (string)$value->attributes()->id;
                $check_parent = (string)$value->attributes()->parent_id;

                $test = $value->attributes()->parentId; 
                if (!empty($check_parent))
                {
                    $category['parent_id'] = (string)$value->attributes()->parent_id;
                }        
                else
                {
                    $category['parent_id'] = (string)$value->attributes()->parentId;        
                }

                if ($category['parent_id'] === '')
                {
                    $category['parent_id'] = $category['id'];
                }

                $category_map += [$category['id']=>$category['parent_id']];
              	    
            }

            $count = 0;
            foreach ($xml->shop->offers->offer as $value) {             
                $categoryId= $value->categoryId;
                $categoryId = (int)$categoryId;

                $check = $category_map[$categoryId];
                if (!in_array($check, $categorypidFilter))
                {
                    unset($xml_buffer->shop->offers->offer[$count]);
                    $count--; 
                    $del_obj_product++; 
                }
                                                  
                $count++;            
            }
               
            $count_category = 0;

            foreach ($xml->shop->categories->category as $value) {             
                $categoryId= (string)$value->attributes()->id;
                $categoryId= (int)$categoryId;

                $check = $category_map[$categoryId];
                if (!in_array($check, $categorypidFilter))
                {
                    unset($xml_buffer->shop->categories->category[$count_category]);
                    $count_category--; 
                    $del_obj_category++;
                }

                $count_category++;
            }

  
            //Search parent_id   
            $res_id = $xml_buffer->xpath("//category/@parent_id"); //Возвращает массив, если нет значение возвращает нулевой массив
            if (count($res_id) !== 0)
            {
                foreach ($res_id as $node)
                {
                    unset($node[0]);
                } 
            }  
 
                
            //Search parentId 
            $resId = $xml_buffer->xpath("//category/@parentId");
            if (count($resId) !== 0)
            {
                foreach ($resId as $node)
                {
                    unset($node[0]);
                } 
            }

            $xml_buffer->asXML($filepath);
            $result_array = ['DeleteProduct' => $del_obj_product, 'DeleteCategory' => $del_obj_category];
            return $result_array;
        }
    }
}
    
