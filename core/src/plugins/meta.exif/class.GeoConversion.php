<?php
//*************************English Description***************************//
// Class to convert Latitude/Longitude Coordinates                       //
// Developed by: Diêgo Garrido de Almeida (diego@brflog.net)             //
// Location: Conselheiro Lafaiete - Minas Gerais / Brazil                //
// License: None, this class can be used without credits                 //
// Recommended use: To convert the Google Earth standard coordinates     //
//                  to Google Maps API standard coordinates, to do this, //
//                  use the method GeoConversao::DMS2Dd.                 //
//                  eg: $GeoConversao->DMS2Dd('45º22\'38"') -> 45.3772   //
//                                                                       //
//                                                                       //
//                                                                       //
// Considerations:                                                       //
// D = Degrees                                                           //
// M = Minutes                                                           //
// S = Seconds                                                           //
// .m = Decimal Minutes                                                  //
// .s = Decimal Seconds                                                  //
//                                                                       //
// DM.m (DMm) = Degrees, Minutes, Decimal Minutes (eg. 45o22.6333)       //
// D.d (Dd) = Degrees, Decimal Degrees (eg. 45.3772o)                    //
// DMS (DMS) = Degrees, Minutes, Seconds (eg. 45o22'38")                 //
//***********************************************************************//

//**************************Descrição em Português*********************//
// Classe para conversão de coordenadas de Latitude e Longitude        //
// Desenvolvida por: Diêgo Garrido de Almeida                          //
// Localização: Conselheiro Lafaiete - Minas Gerais / Brasil           //
// Licença: Nenhuma, podendo ser alterada, sem necessidade de créditos //
// Utilização Recomendada: Conversão das Coordenadas do Google Earth   //
//                         para a API do Google Maps para WEB, através //
//                         do Método GeoConversao::DMS2Dd              //
//                 ex: $GeoConversao->DMS2Dd('45º22\'38"') -> 45.3772  //
//                                                                     //
// Considerações:                                                      //
// D = Degrees (Degrais)                                               //
// M = Minutes (Minutos)                                               //
// S = Seconds (Segundos)                                              //
// .m = Decimal Minutes (Décimos de Minuto)                            //
// .s = Decimal Seconds (Décimos de Segundo)                           //
//                                                                     //
// DM.m (DMm) = Degrees, Minutes, Decimal Minutes (ex. 45o22.6333)     //
// D.d (Dd) = Degrees, Decimal Degrees (ex. 45.3772o)                  //
// DMS (DMS) = Degrees, Minutes, Seconds (ex. 45o22'38")               //
//*********************************************************************//


Class GeoConversion{

   public $negative = FALSE;
   public $real = FALSE;
   public $negative_path = '';

   private function is_negative(&$string)
   {
      if ($string[0] == '-') {
        $this->negative = TRUE;
        $string = str_replace('-','',$string);
        $this->negative_path = '-';
      }
      $real = TRUE;
   }

   private function replace_special_chars(&$string,$decimal)
   {
       for ($I = 0 ; $I < strlen($string) ; $I++) {
         $not_decimal = $decimal == FALSE ? ($string[$I] != '.') : TRUE;
         if (!is_numeric($string[$I]) && $not_decimal && $string[$I] != ' ') {
           $string[$I] = ';';
         } else if ($string[$I] == ' ') {
           $string[$I] = '';
         }
       }
   }

   private function SepDMS($DMS)
   {
       $this->replace_special_chars($DMS,FALSE);
       $dados = explode(';',$DMS);
       return array('D' => $dados[0],'M' => $dados[1],'S' => $dados[2]);
   }

   private function SepDMm($DMm)
   {
       $this->replace_special_chars($DMm,TRUE);
       $dados = explode(';',$DMm);
       return array('D' => $dados[0],'M' => $dados[1],'m' => $dados[2]);
   }

   private function SepDd($Dd)
   {
       $this->replace_special_chars($Dd,TRUE);
       $dados = explode(';',$Dd);
       return array('D' => $dados[0],'d' => $dados[1]);
   }

   public function DMS2DMm($DMS)
   {
       $this->is_negative($DMS);

       $array_DMm = array('D' => '','M' => '','m' => '');
       $array_DMS = $this->SepDMS($DMS);

       $array_DMm['m'] = $array_DMS['S']/60;
       $array_DMm['M'] = $array_DMS['M'];
       $array_DMm['D'] = $array_DMS['D'];

       return $this->negative_path.$array_DMm['D'].'º'.($array_DMm['M'] + $array_DMm['m']);
   }

   public function DMm2Dd($DMm)
   {
       $this->is_negative($DMm);

       $array_Dd = array('D' => '','d' => '');
       $array_DMm = $this->SepDMm($DMm);

       $array_Dd['d'] = ($array_DMm['M'].'.'.$array_DMm['m'])/60;
       $array_Dd['D'] =  $array_DMm['D'];

       return $this->negative_path.($array_Dd['D'] + $array_Dd['d']);
   }

   public function DMS2Dd($DMS)
   {
       $this->is_negative($DMS);

       $DMm = $this->DMS2DMm($DMS);
       return $this->DMm2Dd($DMm);
   }

   public function DMm2DMS($DMm)
   {
       $this->is_negative($DMm);

       $array_DMS = array('D' => '', 'M' => '', 'S' => '');
       $array_DMm = $this->SepDMm($DMm);

       $str_S = ((0).".".$array_DMm['m']) * 60;

       $array_DMS['S'] = $str_S;
       $array_DMS['M'] = $array_DMm['M'];
       $array_DMS['D'] = $array_DMm['D'];

       return $array_DMS['D'].'°'.$array_DMS['M'].'\''.$array_DMS['S'].'"';
   }

   public function Dd2DMm($Dd)
   {
       $this->is_negative($Dd);

       $array_DMm = array('D' => '','M' => '','m' => '');
       $array_Dd = $this->SepDd($Dd);

       $str_Mm = ((0).".".$array_Dd['d']) * 60;

       $dados_Mm = explode(".",$str_Mm);

       $array_DMm['m'] = $dados_Mm[1];
       $array_DMm['M'] = $dados_Mm[0];
       $array_DMm['D'] = $array_Dd['D'];

       return $this->negative_path.$array_DMm['D']."º ".$array_DMm['M'].".".$array_DMm['m'];
   }

   public function Dd2DMS($Dd)
   {
       $this->is_negative($Dd);

       $DMm = $this->Dd2DMm($Dd);
       return $this->DMm2DMS($DMm);
   }
}
