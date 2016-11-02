# Version 1.3.3
Fix - error context shop and replace by cart->id_shop in validation.php

# Version 1.3.2
Fix - preload context shop in calback Validation

# Version 1.3.1
Update documentation URL to the HiPay portal developer

# Version 1.3.0
New architecture with docker, docker compose, circle ci
Delete in root hipay_tpp
Update futur tag 1.3.0
Update docker-compose
Update circle.yml for integrate Circle CI with docker compose
Add README.md
Update markdown files
Add a wiki - Integration guidelines
Update translation EN/FR

# Version 1.2.0
Fix - birthday is a date valide, we add it in the flux transaction then not
Fix - delete the control about id cart when the customer return in the payment page

# Version 1.1.27.5
update template payment_accept.tpl for tag e-commerce
update multistore for callback by TPP

# Version 1.1.27.4
optimization of page 'confirmation order'

# Version 1.1.27.3
update template order confirm

# Version 1.1.27.2
update the template payment_accept.tpl 
add a sleep's function in the controller Accept.php,
delete the javascript code that reloaded the confirmation page a second time

# Version 1.1.27.1
specific translate en / fr
block the treatment callback 113 after the callback 118
execute treatment callback 116 after the callback 113 (transaction different between 113 and 116)