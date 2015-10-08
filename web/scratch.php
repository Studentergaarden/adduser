


function change_room($name,$nameid)
{
  print "$name is mooved to room: ";
  $newroom = intval(trim(fgets(STDIN)));
  if(check_room($newroom))
    mysql_query("UPDATE name SET room='$newroom' WHERE name_id='$nameid';");
  print "\n";
}
function occupied_room($room)
{
  $r=mysql_query("SELECT * from name WHERE room='$room' AND (status='normal');");
  return (0<mysql_num_rows ($r));
}
function check_room($room)
{
  if(!occupied_room($room))
    return True;
  $r=mysql_query("SELECT * from name WHERE room='$room' AND (status='normal');");
  $row=mysql_fetch_array($r);
  print "{$row['name']}({$row['name_id']}) allready lives in room $room:\n";
  $repeat=True;
  while($repeat)
    {
      $repeat=False;
      print "  1) {$row['name']} is mooved out of SG\n";
      print "  2) {$row['name']} is mooved to another room\n";
      print "  3) {$row['name']} is on 'orlov'\n";
      print "  4) {$row['name']} is a 'udlejer'\n";		  
      print "  5) $room is the wrong room\n";
      $val = intval(trim(fgets(STDIN)));
      switch ($val) {
      case 1:
	mysql_query("UPDATE name SET status='flyttet' WHERE name_id='{$row['name_id']}';");
	return True;
	break;
      case 2:
	change_room($row['name'],$row['name_id']);
	return True;
	break;
      case 3:
	mysql_query("UPDATE name SET status='orlov' WHERE name_id='{$row['name_id']}';");
	return True;
	break;
      case 4:
	mysql_query("UPDATE name SET status='udlejer' WHERE name_id='{$row['name_id']}';");
	return True;
	break;
      case 5:
	$returnval=1;
	return False;
	break;
      default:
	print "None of the above";
	$repeat=True;
      }
    }
  return False;
}
