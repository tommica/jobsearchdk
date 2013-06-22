<?php
// Set proper headers
header('Content-type: application/json');

// Include what is needed
include 'simple_html_dom.php';

// Data contains the output, that will be encoded as json
$data = array("error" => false, "errorText" => "", "result" => "");

// Allowed services
$services = array('jobindex', 'jobnet', 'jobunivers');

// There should be atleast 4 variables in GET - word, page, service, zip
if( $_GET['word'] && $_GET['page'] && $_GET['service'] && $_GET['zip'] ) {
  // TODO: Escape This String!
  $word = $_GET['word'];

  // Check that page & zip is a number
  $page = is_numeric( $_GET['page'] ) ? $_GET['page'] : 1;
  $zip = is_numeric( $_GET['zip'] ) ? $_GET['zip'] : "";

  // Figure out the region - service specific functions figure out rest from this
  // https://da.wikipedia.org/wiki/Danske_postnumre
  $region = ($zip >= 1000 && $zip <= 2999) ? 'storkøbenhavn' :
            (($zip >= 3000 && $zip <= 3699) ? 'nordsjælland' :
            (($zip >= 3700 && $zip <= 3799) ? 'bornholm' :
            (($zip >= 3800 && $zip <= 3899) ? 'færøe' :
            // Here should be the 3900-3999
            (($zip >= 4000 && $zip <= 4999) ? 'Østsjælland, Midt- og Vestsjælland, Sydsjælland, Lolland-Falster og Møn' : // This is here for temporarily, until I figure out the right regions and postnumberrs for these places
//            (($zip >= 4000 && $zip <= 4999) ? 'sydsjælland inkl. møn' : // SPLIT THIS
//            (($zip >= 4000 && $zip <= 4999) ? 'vestsjælland' :
//            (($zip >= 4000 && $zip <= 4999) ? 'midtsjælland' :

            (($zip >= 5000 && $zip <= 5999) ? 'fyn' :
            (($zip >= 6000 && $zip <= 6999) ? 'syd- og sønderjylland' :

            (($zip >= 7000 && $zip <= 7999) ? 'Nordvestjylland, Vestjylland, dele af Midtjylland, dele af Sydjylland samt det sydlige Østjylland' : // This is here for temporarily, until I figure out the right regions and postnumberrs for these places
//            (($zip >= 7000 && $zip <= 7999) ? 'østjylland' : //FIX this is like west of this place for real?! BUT ALSO SPLIT
//            (($zip >= 7000 && $zip <= 7999) ? 'midtjylland' :
//            (($zip >= 7000 && $zip <= 7999) ? 'vestjylland' :

            (($zip >= 8000 && $zip <= 8999) ? 'østjylland' :
            (($zip >= 9000 && $zip <= 9999) ? 'nordjylland' : 
            'error')))))))));
            // For the fixed areas
//            'error')))))))))))));
            // I feel so dirt after this... TODO: Make this ternary to an if-else

  // Handle the proper service
  if( in_array($_GET['service'], $services) ) {
    // When we use $variable(), it uses it as an function - $var = 'thisisastring' -> $var() -> thisisastring()
    $service = $_GET['service'].'Scraper';
    $data['result'] = $service($page, $word, $zip, $region);
  } else {
    $data['error'] = true;
    $data['errorText'] = 'Service is not supported';
  }

} else {
  $data['error'] = true;
  $data['errorText'] = 'Missing variables, check your syntax';
}

echo json_encode($data, JSON_HEX_AMP);


/* FUNCTIONS */
function jobindexScraper($page, $word, $zip, $region) {
  // Set the URL
  $url = 'http://www.jobindex.dk/';
  $fetchUrl = $url.'/cgi/jobsearch.cgi?page='.$page.'&q='.$word.'&zipcodes='.$zip;

  // Data holds the output
  $data = array();

  // Get the HTML
  $html = file_get_html( $fetchUrl );

  foreach( $html->find('div.PaidJob') as $element ) {
    $temp = array();
    $title = $element->find('a', 1);

    $temp['url'] = $url.$title->href;
    $temp['title'] = utf8_encode($title->plaintext);

    $temp['company'] = utf8_encode($element->find('.jobtext', 0)->plaintext);
    $temp['location'] = "";

    $data[] = $temp;
  }
  
  foreach( $html->find('div.jix_robotjob') as $element ) {
    $temp = array();
    $title = $element->find('a', 0);

    $temp['url'] = $title->href;
    $temp['title'] = utf8_encode($title->plaintext);

    $temp['location'] = utf8_encode($element->find('b', 1)->plaintext);
    $temp['company'] = utf8_encode($element->find('b', 0)->plaintext);

    $data[] = $temp;
  }
  

  return $data;
}

function jobnetScraper($page, $word, $zip, $region) {
  // Jobnet counts pages as start numbers, and force it in 20 apps per page
  // Apparently I can use whatever amount I want when using this url, but I think I'll keep it in 20 still
  $page = ( ($page-1) * 20 ) + 1;
  // Set the URL
  $url = 'https://job.jobnet.dk/';
  $time = mktime();
  $fetchUrl = $url.'/FindJobService/V1/Gateway.ashx/annonce?_='.$time.'&fritekst='.$word.'&postdistrikt='.$zip.'&start='.$page.'&antal=20&sortering=match&format=json&omegn=1';

  // Data holds the output
  $data = array();

  // Get the JSON
  $html = file_get_contents( $fetchUrl );
  $html = json_decode( $html );

  foreach( $html->JobPostingDigests as $element ) {
    $temp = array();

    $temp['title'] = $element->Headline;
    $temp['company'] = $element->HiringOrgName;
    $temp['location'] = $element->WorkLocation;

    // Jobnet has both internal and external applications - internals need url+id, external just url
    $temp['url'] = ($element->DetailsUrl) ? $element->DetailsUrl : 'https://job.jobnet.dk/CV/FindJob/details.aspx/'.$element->Id;

    $data[] = $temp;
  }
  
  return $data;
}

function jobuniversScraper($page, $word, $zip, $region) {
  // Jobunvers uses base64 for encoding the url, with some weird syntax - example:
  // ?query=djAuMXxQTjoxfFBTOjIwfENSOkZyZWV0ZXh0OnRla25pc2t8djAuMQ==&params=cXVlcnlmaWx0ZXI6
  // Map regions:
  $region = $region == 'nordjylland' ? '^Nordjylland$' : 
            $region == 'østjylland' ? '^Østjylland$' :
            $region == 'vestjylland' ? '^Vestjylland$' :
            $region == 'midtjylland' ? '^Midtjylland$' :
            $region == 'syd- og sønderjylland' ? '^"Syd- og Sønderjylland"$' :
            $region == 'fyn' ? '^Fyn$' :
            $region == 'midtsjælland' ? '^Midtsjælland$' :
            $region == 'vestsjælland' ? '^Vestsjælland$' :
            $region == 'sydsjælland inkl. møn' ? '^"Sydsjælland inkl. Møn"$' :
            $region == 'bornholm' ? '^Bornholm$' :
            $region == 'nordsjælland' ? '^Nordsjælland$' :
            $region == 'østjylland' ? '^Østjylland$' :

            // This are here until I've done the splits in the main region check
            $region == 'Nordvestjylland, Vestjylland, dele af Midtjylland, dele af Sydjylland samt det sydlige Østjylland' ? '^Midtjylland$_Midtjylland~^Vestjylland$_Vestjylland' :
            $region == 'Østsjælland, Midt- og Vestsjælland, Sydsjælland, Lolland-Falster og Møn' ? '^Vestsjælland$_Vestsjælland~^"Sydsjælland inkl. Møn"$_Sydsjælland inkl. Møn~^Nordsjælland$_Nordsjælland' :
            // //This are here until I've done the splits in the main region check
            
            '^Storkøbenhavn$';

  $params = base64_encode('queryfilter:|workarea:0|workarea_more:0|Joblocation4:0|Joblocation4_more:0|Jobtype:0|Jobtype_more:0');
  $query = base64_encode('v0.1|RV:Quicklisting|SO:Relevans|PN:1|PS:20|CR:Freetext:'.$word.'|NA:Joblocation4:'.$region.'|PN:'.$page.'|PS:20|v0.1');

  // Set the URL
  $url = 'http://www.jobunivers.dk/';
  $fetchUrl = $url.'/resultat.aspx?query='.$query.'&params='.$params;

  // Data holds the output
  $data = array();

  // Get the HTML
  $html = file_get_html( $fetchUrl );

  foreach( $html->find('div.ResultListElementEven, div.ResultListElementOdd') as $element ) {
    // Apparently here I don't need urf8_encode for some reason - I guess they provide the content with it already
    $temp = array();
    $title = $element->find('.headingContainer h2 a', 0);

    $temp['url'] = $url.$title->href;
    $temp['title'] = ($title->plaintext);

    $temp['company'] = ($element->find('.companyContainer .company', 0)->plaintext);
    $temp['location'] = ($element->find('.LocationContainer .Location', 0)->plaintext);

    $data[] = $temp;
  }

  return $data;
}
?>
