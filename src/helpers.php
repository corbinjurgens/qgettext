<?php

// Requires QGetText facade
if (!function_exists('t')){
   function t(string $original, ...$strintf){
      return sprintf(QGetText::gettext($original), ...$strintf);
   }
}

if (!function_exists('n')){
   function n(string $original, string $plural, int $value, ...$strintf){
      return sprintf(QGetText::ngettext($original, $plural, $value), ...$strintf);
   }
}

if (!function_exists('p')){
   function p(string $context, string $original, ...$strintf){
      return sprintf(QGetText::pgettext($context, $original), ...$strintf);
   }
}

if (!function_exists('d')){
   function d(string $domain, string $original, ...$strintf){
      return sprintf(QGetText::dgettext($domain, $original), ...$strintf);
   }
}

if (!function_exists('dn')){
   function dn(string $domain, string $original, string $plural, int $value, ...$strintf){
      return sprintf(QGetText::dngettext($domain, $original, $plural, $value), ...$strintf);
   }
}

if (!function_exists('dp')){
   function dp(string $domain, string $context, string $original, ...$strintf){
      return sprintf(QGetText::dpgettext($domain, $context, $original), ...$strintf);
   }
}

if (!function_exists('np')){
   function np(string $context, string $original, string $plural, int $value, ...$strintf){
      return sprintf(QGetText::npgettext($context, $original, $plural, $value), ...$strintf);
   }
}

if (!function_exists('dnp')){
   function dnp(string $domain, string $context, string $original, string $plural, int $value, ...$strintf){
      return sprintf(QGetText::dnpgettext($domain, $context, $original, $plural, $value), ...$strintf);
   }
}

if (!function_exists('noop')){
   function noop(string $original, ...$strintf){
      return sprintf(QGetText::noop($original), ...$strintf);
   }
}