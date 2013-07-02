<?php
// Set proper headers
header('Content-type: application/json');

// Include what is needed
include 'simple_html_dom.php';

// Data contains the output, that will be encoded as json
$data = array("error" => false, "errorText" => "", "result" => "");

// Allowed services
$services = array('jobindex', 'jobnet', 'jobunivers', 'jobzonen', 'ofir', 'monster');

// There should be atleast 4 variables in GET - word, page, service, zip
if( $_GET['word'] && $_GET['page'] && $_GET['service'] && $_GET['zip'] ) {
  // TODO: Escape This String!
  $word = $_GET['word'];

  // Check that page & zip is a number
  $page = is_numeric( $_GET['page'] ) ? $_GET['page'] : 1;
  $zip = is_numeric( $_GET['zip'] ) ? $_GET['zip'] : "1000";

  // Handle the proper service
  if( in_array($_GET['service'], $services) ) {
    // When we use $variable(), it uses it as an function - $var = 'thisisastring' -> $var() -> thisisastring()
    $service = $_GET['service'].'Scraper';
    $data['result'] = $service($page, $word, $zip, $_GET['service']);
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
function jobindexScraper($page, $word, $zip, $service) {
  // Set the URL
  $url = 'http://www.jobindex.dk/';
  $fetchUrl = $url.'/cgi/jobsearch.cgi?page='.$page.'&q='.$word.'&zipcodes='.$zip;

  // Data holds the output
  $data = array();

  // Get the HTML
  $html = file_get_html( $fetchUrl );

  if($html == true) {
    foreach( $html->find('div.PaidJob') as $element ) {
      $temp = array();
      $title = $element->find('a', 1);

      $temp['service'] = $service;
      $temp['url'] = $url.$title->href;
      $temp['title'] = utf8_encode($title->plaintext);

      $temp['company'] = utf8_encode($element->find('.jobtext', 0)->plaintext);
      $temp['location'] = "";

      $data[] = $temp;
    }
    
    foreach( $html->find('div.jix_robotjob') as $element ) {
      $temp = array();
      $title = $element->find('a', 0);

      $temp['url'] = $url.$title->href;
      $temp['title'] = utf8_encode($title->plaintext);

      $temp['location'] = utf8_encode($element->find('b', 1)->plaintext);
      $temp['company'] = utf8_encode($element->find('b', 0)->plaintext);

      $data[] = $temp;
    }
  }
  

  return $data;
}

function jobnetScraper($page, $word, $zip, $service) {
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

  if($html == true) {
    $html = json_decode( $html );

    foreach( $html->JobPostingDigests as $element ) {
      $temp = array();

      $temp['service'] = $service;
      $temp['title'] = $element->Headline;
      $temp['company'] = $element->HiringOrgName;
      $temp['location'] = $element->WorkLocation;

      // Jobnet has both internal and external applications - internals need url+id, external just url
      $temp['url'] = ($element->DetailsUrl) ? $element->DetailsUrl : 'https://job.jobnet.dk/CV/FindJob/details.aspx/'.$element->Id;

      $data[] = $temp;
    }
  }
  
  return $data;
}

function jobuniversScraper($page, $word, $zip, $service) {
  // Jobunvers uses base64 for encoding the url, with some weird syntax - example:
  // ?query=djAuMXxQTjoxfFBTOjIwfENSOkZyZWV0ZXh0OnRla25pc2t8djAuMQ==&params=cXVlcnlmaWx0ZXI6
  // Map regions:
  $region = '^Storkøbenhavn$_Storkøbenhavn';
  if( $zip >= 1000 && $zip <= 2999 ) {
    $region = '^Storkøbenhavn$_Storkøbenhavn';
  } elseif( $zip >= 3000 && $zip <= 3699 ) {
    $region = '^Nordsjælland$_Nordsjælland';
  } elseif( $zip >= 3700 && $zip <= 3799 ) {
    $region = '^Bornholm$_Bornholm';
  } elseif( $zip >= 3800 && $zip <= 3899 ) {
    // This should be Færøe, but their service does not seem to support them
    $region = $region;
  } elseif( $zip >= 3900 && $zip <= 3999 ) {
    // Greenland, but not supported by the service
    $region = $region;
  } elseif( $zip >= 4000 && $zip <= 4999 ) {
    // Multiple areas, should be split
    $region = '^Midtsjælland$_Midtsjælland~^Vestsjælland$_Vestsjælland~^"Sydsjælland inkl. Møn"$_Sydsjælland inkl. Møn';
  } elseif( $zip >= 5000 && $zip <= 5999 ) {
    $region = '^Fyn$_Fyn';
  } elseif( $zip >= 6000 && $zip <= 6999 ) {
    $region = '^"Syd- og Sønderjylland"$_Syd- og Sønderjylland';
  } elseif( $zip >= 7000 && $zip <= 7999 ) {
    // Multiple areas, should be split
    $region = '^Midtjylland$_Midtjylland~^Vestjylland$_Vestjylland';
  } elseif( $zip >= 8000 && $zip <= 8999 ) {
    $region = '^Østjylland$_Østjylland';
  } elseif( $zip >= 9000 && $zip <= 9999 ) {
    $region = '^Nordjylland$_Nordjylland';
  } 

  $params = base64_encode('queryfilter:|workarea:0|workarea_more:0|Joblocation4:0|Joblocation4_more:0|Jobtype:0|Jobtype_more:0');
  $query = base64_encode('v0.1|RV:Quicklisting|SO:Relevans|CR:Freetext:'.$word.'|NA:Joblocation4:'.$region.'|PN:'.$page.'|PS:20|v0.1');

  // Set the URL
  $url = 'http://www.jobunivers.dk/';
  $fetchUrl = $url.'/resultat.aspx?query='.$query.'&params='.$params;

  // Data holds the output
  $data = array();

  // Get the HTML
  $html = file_get_html( $fetchUrl );

  if($html == true) {
    foreach( $html->find('div.ResultListElementEven, div.ResultListElementOdd') as $element ) {
      // Apparently here I don't need urf8_encode for some reason - I guess they provide the content with it already
      $temp = array();
      $title = $element->find('.headingContainer h2 a', 0);
      $temp['service'] = $service;

      $temp['url'] = $url.$title->href;
      $temp['title'] = ($title->plaintext);

      $temp['company'] = ($element->find('.companyContainer .company', 0)->plaintext);
      $temp['location'] = ($element->find('.LocationContainer .Location', 0)->plaintext);

      $data[] = $temp;
    }
  }

  return $data;
}

function jobzonenScraper($page, $word, $zip, $service) {
  // Jobunvers uses base64 for encoding the url, with some weird syntax - example:
  // ?query=djAuMXxQTjoxfFBTOjIwfENSOkZyZWV0ZXh0OnRla25pc2t8djAuMQ==&params=cXVlcnlmaWx0ZXI6
  // Map regions:
  $region = '/hovedstaden';
  if( $zip >= 1000 && $zip <= 2999 ) {
    $region = '/hovedstaden';
  } elseif( $zip >= 3000 && $zip <= 3699 ) {
    $region = '/region-sjaelland';
  } elseif( $zip >= 3700 && $zip <= 3799 ) {
    $region = '/bornholm';
  } elseif( $zip >= 3800 && $zip <= 3899 ) {
    // This should be Færøe, but their service does not seem to support them
    $region = $region;
  } elseif( $zip >= 3900 && $zip <= 3999 ) {
    // Greenland, but not supported by the service
    $region = $region;
  } elseif( $zip >= 4000 && $zip <= 4999 ) {
    // Multiple areas, should be split
    $region = '/region-sjaelland';
  } elseif( $zip >= 5000 && $zip <= 5999 ) {
    $region = '/fyn';
  } elseif( $zip >= 6000 && $zip <= 6999 ) {
    $region = '/sydjylland';
  } elseif( $zip >= 7000 && $zip <= 7999 ) {
    // Multiple areas, should be split
    $region = '/midtjylland';
  } elseif( $zip >= 8000 && $zip <= 8999 ) {
    $region = '/midtjylland';
  } elseif( $zip >= 9000 && $zip <= 9999 ) {
    $region = '/nordjylland';
  } 

  // Figure out offset
  $offset = 0 + ( ($page-1) * 20 );

  // Set the URL
  $url = 'http://www.jobzonen.dk/';
  $fetchUrl = $url.'/jobsoeger/job/danmark'.$region.'?SearchTerms='.$word.'&offset='.$offset.'&length=20';

  // Data holds the output
  $data = array();

  // Get the HTML
  $html = file_get_html( $fetchUrl );

  if($html == true) {
    foreach( $html->find('.job-posting') as $element ) {
      // Apparently here I don't need urf8_encode for some reason - I guess they provide the content with it already
      $temp = array();
      $title = $element->find('.column2 h2 a', 0);
      $temp['service'] = $service;

      $temp['url'] = $url.$title->href;
      $temp['title'] = ($title->plaintext);

      $temp['company'] = "";
      $temp['location'] = ($element->find('.column2 .jobLocation a', 0)->plaintext);

      $data[] = $temp;
    }
  }

  return $data;
}

function ofirScraper($page, $word, $zip, $service) {
  // Ofir has basically the same way of handling thing as  jobunivers
  // Map regions:
  $region = '^Storkøbenhavn$_';
  if( $zip >= 1000 && $zip <= 2999 ) {
    $region = '^Storkøbenhavn$_';
  } elseif( $zip >= 3000 && $zip <= 3699 ) {
    $region = '^Nordsjælland$_';
  } elseif( $zip >= 3700 && $zip <= 3799 ) {
    $region = '^"Bornholm og Erteholmene"$_';
  } elseif( $zip >= 3800 && $zip <= 3899 ) {
    // This should be Færøe, but their service does not seem to support them
    $region = $region;
  } elseif( $zip >= 3900 && $zip <= 3999 ) {
    // Greenland, but not supported by the service
    $region = '^Grønland$_';
  } elseif( $zip >= 4000 && $zip <= 4999 ) {
    // Multiple areas, should be split
    $region = '^Vestsjælland$__^Midtsjælland$__^"Sydsjælland inkl. Møn"$__^"Lolland & Falster"$_';
  } elseif( $zip >= 5000 && $zip <= 5999 ) {
    $region = '^Fyn$__^"Sydfynske øer"$_';
  } elseif( $zip >= 6000 && $zip <= 6999 ) {
    $region = '^"Syd- og Sønderjylland"$_';
  } elseif( $zip >= 7000 && $zip <= 7999 ) {
    // Multiple areas, should be split
    $region = ':^Midtjylland$__^Vestjylland$_';
  } elseif( $zip >= 8000 && $zip <= 8999 ) {
    $region = '^Østjylland$_';
  } elseif( $zip >= 9000 && $zip <= 9999 ) {
    $region = '^Nordjylland$__^Himmerland$_';
  } 

  $params = base64_encode('queryfilter:');
  $query = base64_encode('v0.1|CR:Freetext:'.$word.'|NA:location:'.$region.'|PN:'.$page.'|PS:20|v0.1');

  // Set the URL
  $url = 'http://www.ofir.dk/';
  $fetchUrl = $url.'/Resultat.aspx?soegeord='.$word.'&query='.$query.'&params='.$params;

  // Data holds the output
  $data = array();

  // Get the HTML
  $html = file_get_html( $fetchUrl );

  if($html == true) {
    foreach( $html->find('div.SearchResultItem') as $element ) {
      // Apparently here I don't need urf8_encode for some reason - I guess they provide the content with it already
      $temp = array();
      $title = $element->find('.Heading a', 0);
      $temp['service'] = $service;

      $temp['url'] = $url.$title->href;
      $temp['title'] = ($title->plaintext);

      $temp['company'] = ($element->find('.CompanyName', 0)->plaintext);
      $temp['location'] = ($element->find('Location', 0)->plaintext);

      $data[] = $temp;
    }
  }

  return $data;
}

function monsterScraper($page, $word, $zip, $service) {
  // Monster requires that the city is written, not postcode
  $city = 'http://geo.oiorest.dk/postnumre/'.$zip.'.json';
  $city_json = file_get_contents( $city );
  $city_json = json_decode($city_json);
  $city = $city_json->navn;

  // Set the URL
  $context = stream_context_create(array(
    'http'=>array(
      'method'=>"GET",                
      'header'=>"Accept: text/html,application/xhtml+xml,application/xml\r\n" .
                "Accept-Charset: ISO-8859-1,utf-8\r\n" .
                "Accept-Language: en-US,en;q=0.8\r\n",
      'user_agent'=>"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.116 Safari/537.36"              
  )
  )); 

  $url = 'http://m.monster.dk/';
  $fetchUrl = $url.'JobSearch/LoadPage?pageSize=20&radius=50&searchType=1&country=dk&keywords='.$word.'&where='.$city.'&page='.$page;

  // Data holds the output
  $data = array();

  // Get the HTML
  $html = file_get_contents( $fetchUrl, false, $context );

  if($html == true) {
    $html = json_decode( $html );

    foreach( $html as $element ) {
      $temp = array();

      $temp['service'] = $service;
      $temp['title'] = $element->Title;
      $temp['company'] = $element->CompanyNameText;
      $temp['location'] = $element->City;

      // Jobnet has both internal and external applications - internals need url+id, external just url
      $temp['url'] = ($element->JobViewUrl) ? $element->DetailsUrl : 'https://monster.dk/'.$element->JobId;

      $data[] = $temp;
    }
  }

  return $data;
}
?>
