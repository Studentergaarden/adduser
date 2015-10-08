<?php
  $name_id = array_key_exists("n", $_GET) ? $_GET['n'] : false;
  $room    = array_key_exists("r", $_GET) ? $_GET['r'] : false;
?>
<!DOCTYPE html>
<html>
<head>
<script>
var name_id = <?php echo $name_id;?>;
var room = <?php echo $room;?>;

function loadXMLDoc(action)
{
  var xmlhttp;

  if (window.XMLHttpRequest)
  {// code for IE7+, Firefox, Chrome, Opera, Safari
    xmlhttp=new XMLHttpRequest();
  }
  else
  {// code for IE6, IE5
    xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
  }
  
  xmlhttp.onreadystatechange=function()
  {
    if (xmlhttp.readyState==4 && xmlhttp.status==200)
    {
        var str = xmlhttp.responseText;

        if(str == "success"){
          document.getElementById("dialog").innerHTML = "Bruger er oprettet og alt er ok. Tryk <a href='/createUser.php'>her</a> for at oprette en ny bruger.";
        }else{
          var res = str.split(":"); 
            
          name_id = res[0];
          name = res[1];

          if(name_id == -1){
            document.getElementById("dialog").innerHTML = "Der er sket en fejl, prøv at løse det i SAS eller kontakt NU.";
            return;
          }

          document.getElementById("name").innerHTML = name;
        }
    }
  }

  var url = "changeRoomBackend.php?a="+action+"&n="+name_id+"&r="+room;
  xmlhttp.open("GET",url,true);
  xmlhttp.send();
}

function newRoom(){
  var tmp_room = document.getElementById("room_in").value;

  if(!(tmp_room % 1 === 0) || tmp_room === ""){
    alert("Der mangler et indtastet værelsesnummer.");
    return;
  }

  room = tmp_room;
  document.getElementById("room").innerHTML = room;
  
  loadXMLDoc("inte");
}

window.onload = function() {
  document.getElementById("room").innerHTML = room;
  loadXMLDoc("inte");
};

</script>
</head>
<body>

<div id="dialog">
  <table>
    <tr>
      <td>Hvad er der sket med <span id="name"></span> på værelse <span id="room"></span><br></td>
    </tr>
    <tr>
      <td>
        <button type="button" onclick="loadXMLDoc('flyt')">
          Flyttet
        </button>
      </td>
    </tr>
    <tr>
      <td>
        <button type="button" onclick="loadXMLDoc('udl')">
          Udlejer
        </button>
      </td>
    </tr>
    <tr>
      <td>
        <button type="button" onclick="loadXMLDoc('orl')">
          Orlov
        </button>
      </td>
    </tr>
    <tr>
      <td>
        <button type="button" onclick="newRoom()">
          Skiftet værelse
        </button>
      </td>
      <td>
        <input type="text" id="room_in" />
        </button>
      </td>
    </tr>
  </table>
</div>

</body>
</html>
