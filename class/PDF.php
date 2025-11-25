<?php
namespace Koyabu\Webapi;
use Dompdf\Dompdf;

class PDF {
   
   public $dompdf;
   public $options;

   public function __construct() {
      $this->dompdf = new \Dompdf\Dompdf(array('enable_remote' => true));
   }

   public function setOptions($options) {
      $options['paper_size'] = $options['paper_size'] ?? 'A4';
      $options['orientation'] = $options['orientation'] ?? 'portrait';
      $options['filename'] = $options['filename'] ?? 'output.pdf';
      $this->options = $options;
   }
   
   public function run() {
      $this->dompdf->setPaper($this->options['paper_size'], $this->options['orientation']);
      $this->dompdf->loadHTML($this->options['html'] ?? '');
      $this->dompdf->render();
      if ($this->options['htmlview'] == true) {
         echo $this->options['html'] ?? '';
         return;
      }

      if ($this->options['download'] == true) {
         $this->dompdf->stream();
      } else {
         $output = $this->dompdf->output();
         if ($this->options['writeToFile'] == true) {
            file_put_contents($this->options['filename'], $output);
         } else {
            if (ob_get_length()) ob_clean();
            header('Content-type:application/pdf');
            header('Content-disposition: inline; filename="'.basename($this->options['filename']).'"');
            header('content-Transfer-Encoding:binary');
            header('Accept-Ranges:bytes');
            // ob_end_clean();
            echo $output;
         }
      }
   }
}

?>