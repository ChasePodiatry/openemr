#IfMissingColumn users krb5_principle

ALTER TABLE `users` ADD `krb5_principle` VARCHAR(256);

#EndIF
