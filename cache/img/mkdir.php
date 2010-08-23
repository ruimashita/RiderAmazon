<?php

mkdir("unknown", 0777);

// 00000 - 00099
for($i=0; $i<100; $i++){
  mkdir("000".sprintf("%02d", $i), 0777);
}

// 00400 - 00499
for($i=400; $i<500; $i++){
  mkdir("00".$i, 0777);
}

// B0000 - B0099
for($i=0; $i<100; $i++){
  mkdir("B00".sprintf("%02d", $i), 0777);
}

// B0000 - B009Y
for ($j=0; $j<10; $j++){
  for($i=A; $i<Z; $i++){
    mkdir("B00".$j.$i, 0777);
  }
}

// B000Z - B009Z
for ($j=0; $j<10; $j++){
  mkdir("B00".$j."Z", 0777);
}

?>