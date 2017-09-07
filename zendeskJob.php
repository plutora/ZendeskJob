<?php 

echo "Starting Zendeskk - PlutoraTest connection... \n";
getZDIDs();

function getZDIDs()
{

        echo "connecting to Zendesk .. ";
        $strykaIDs = []; //declare empty array to insert stryka IDs

        // get Stryka IDs from tickets 
        $currURL = "https://plutora.zendesk.com/api/v2/search.json?query=type%3Aticket+status%3Csolved";

  while ($currURL != ''){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $currURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_USERPWD, $ZendeskUsername . ":". $ZendeskPassword);
        $result = curl_exec($ch);
        if (curl_errno($ch))
        {
                echo "couldn't connect :(( \n";
                echo 'Error:' . curl_error($ch);
        }
        else
        {
                echo "connected. \n";
                $resultt = curl_exec($ch);
                $array = json_decode($result, true);
                //print_r($array);
                $external_i = $array['results'];
                $array = json_decode($result, TRUE);
                // go through array (dump) and get ID of ticket and ZD associated
            for ($i = 0; $i < count($array['results']); $i++)
            {
                   $tempS = $array['results'][$i]['custom_fields']['5']["value"];
                   //echo "->" . $tempS . "\n";
                   if (!empty($tempS))
                   {
                     //echo "true";
                     $tempI = $array['results'][$i]["id"];
                     array_push($strykaIDs, $tempI, $tempS);
                   }
                   //echo "Id is " . $array['results'][$i]["id"] . " and Zendesk ID associated is " . $tempS;
                   //echo "\n";
           }
	   //check for next page since Zendesk only returns 100 records at a time
           if (in_array($array["next_page"], $array))
           {
           $currURL = ($array["next_page"]);}
           else {$currURL = '';}
               //echo $url;

                //echo $a;
                        }
  }
         curl_close($ch);
         echo "done!! \nsuccess.. " .count($strykaIDs). " records found. \n";
         echo "\n";
         accessStryka($strykaIDs);
}

function accessStryka($strykaIDs)
{

        echo "accessing token... \n";
        $client_id = 'XXXXXXXXXXXXXXXXXXXXXXXXXX';
        $client_secret = 'XXXXXXXXXXXXXXXXXXXXXXXXXX';
        $grant_type = 'password';
        $username = 'username@plutora.com';
        $password = 'PlutoraPassword';


        // get token
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => "https://oauthps.plutora.com/oauth/token",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
               CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "client_id=$client_id&client_secret=$client_secret&grant_type=$grant_type&username=$username&password=$password",
        CURLOPT_HTTPHEADER => array("cache-control: no-cache")));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        $access_token;
        if ($err)
        {
                echo "couldn't access token :( \n";
                echo "cURL Error #:" . $err;
        }
        else
        {
                echo "accessed token succesfully.. \n";
                $arr = json_decode($response, true);
                $access_token = $arr['access_token'];
        }

        // loop through PlutoraTest and make url, then grab status
        $bodyyy = "{\"tickets\": [";

        echo "getting statuses \n";
        for ($i = 1; $i < count($strykaIDs); $i+=2)
        {
                //echo "getting statuses \n";
                $defect_url = "https://apips.plutora.com/defects/GetByDisplayId/";
                $defect_url.= $strykaIDs[$i];
                //echo $defect_url . "\n";
                // echo $value;
                $curl = curl_init();
                curl_setopt_array($curl, array(
                CURLOPT_URL => $defect_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array("authorization: bearer $access_token","cache-control: no-cache","content-type: application/json") ,));
                $response2 = curl_exec($curl);
                $res_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                //echo $res_code ."\n";
                $err = curl_error($curl);
                curl_close($curl);
                        if ($err or $res_code !== 200)
                        {
                                //echo "cURL Error #:" . $err;
                                echo "bad url on - ".($strykaIDs[$i]) ."\n";
                        }
                        else
                        {
                                $arr2 = json_decode($response2, true);
                                                                $temp = $arr2['Data'][0]['Status']['Value']; //id
                                //$temp2 = $arr2['Data'][0]['Fields'][4]['StringValue']; //status
                                $temp2 = $strykaIDs[$i-1];

                                $bodyyy.= trim("{ \"id\": $temp2, \"custom_fields\":[{\"id\":80696707, \"value\":\"$temp\"}]},");
                                } //end url loop
                        }
                $bodyyy = rtrim($bodyyy, ',');
                $bodyyy.= "]}";
                //echo $bodyyy ."\n";

        updateZD($bodyyy);
}

function updateZD($bodyyy)
{
        echo "Updating values on Zendesk...\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://plutora.zendesk.com/api/v2/tickets/update_many.json");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyyy);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_USERPWD, $ZendeskUsername . ":" . $ZendeskPassword);
        $headers = array();
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
               if (curl_errno($ch))
        {
                echo "not connected, update failed :(";
                echo 'Error:' . curl_error($ch);
        }

        echo "Success :) \n";
        $result = curl_exec($ch);
        curl_close($ch);
        echo $result;
}

echo "FINISHED\n";

?>