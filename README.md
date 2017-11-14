# moota-prestashop

## Development

To start developing, first run `composer install --prefer-dist --no-dev` on the 
root folder of this module package.  

All composer packages will be installed to: `library/`

Then run:  
  ```bash
  ./link.sh <TARGET>
  ```
where `<TARGET>` is your root prestashop installation directory,  
_AND_ there is one folder named `modules` in it.

## Bundle generation
Run
  ```bash
  ./bundle.sh
  ```
and then find for these two files:
  - `mootapay.zip`
  - `mootapay.tar.bz2`

these are the files that will be uploaded to github's release process

## Dokumentasi
Silahkan lihat pada [Wiki](https://github.com/mootaco/moota-prestashop/wiki/Dokumentasi) 
untuk instalasi, pengaturan, dan lainnya
