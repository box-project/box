```sh
$ cd to this directory
$ openssl genrsa -out private-key.pem 2048
$ openssl rsa -in private-key.pem -outform PEM -pubout -out public-key.pem
```
