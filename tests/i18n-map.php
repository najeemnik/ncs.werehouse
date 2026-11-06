<?php
require __DIR__.'/bootstrap.php';$map=[];
foreach(get_translation_dictionary() as $key=>$langs)if(isset($langs['en'],$langs['da']))$map[$langs['da']]=$langs['en'];
get_phrase_translation('','en');$vars=(new ReflectionFunction('get_phrase_translation'))->getStaticVariables();
foreach($vars['phraseMap']??[] as $key=>$langs)if(isset($langs['en']))$map[$key]=$langs['en'];
echo json_encode($map,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
