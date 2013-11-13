-- VirtueMart table SQL script
-- This will install all the tables need to run Svea Web Pay



INSERT INTO `#_extensions` (`extension_id`, `type`, `name`, `element`, `folder`, `access`, `ordering`
, `enabled`, `protected`, `client_id`, `checked_out`, `checked_out_time`, `params`) VALUES
(NULL, 'plugin', 'Svea Invoice', 'svea_invoice', 'vmpayment', 1, 0, 1, 0, 0, 0, '0000-00-00 00:00:00', '');

INSERT INTO `#_extensions` (`extension_id`, `type`, `name`, `element`, `folder`, `access`, `ordering`
, `enabled`, `protected`, `client_id`, `checked_out`, `checked_out_time`, `params`) VALUES
(NULL, 'plugin', 'Svea Payment Plan', 'svea_paymentplan', 'vmpayment', 1, 0, 1, 0, 0, 0, '0000-00-00 00:00:00', '');