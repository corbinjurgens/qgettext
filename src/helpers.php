<?php

// Requires QGetText facade
if (!function_exists('t')){
   function t(string $original){
      return QGetText::gettext($original);
   }
}

if (!function_exists('n')){
   function n(string $original, string $plural, int $value){
      return QGetText::ngettext($original, $plural, $value);
   }
}

if (!function_exists('p')){
   function p(string $context, string $original){
      return QGetText::pgettext($context, $original);
   }
}

if (!function_exists('d')){
   function d(string $domain, string $original){
      return QGetText::dgettext($domain, $original);
   }
}

if (!function_exists('dn')){
   function dn(string $domain, string $original, string $plural, int $value){
      return QGetText::dngettext($domain, $original, $plural, $value);
   }
}

if (!function_exists('dp')){
   function dp(string $domain, string $context, string $original){
      return QGetText::dpgettext($domain, $context, $original);
   }
}

if (!function_exists('np')){
   function np(string $context, string $original, string $plural, int $value){
      return QGetText::npgettext($context, $original, $plural, $value);
   }
}

if (!function_exists('dnp')){
   function dnp(string $domain, string $context, string $original, string $plural, int $value){
      return QGetText::dnpgettext($domain, $context, $original, $plural, $value);
   }
}

if (!function_exists('noop')){
   function noop(string $original){
      return QGetText::noop($original);
   }
}