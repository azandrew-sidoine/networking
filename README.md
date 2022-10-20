# Network utils

This package provide developper with networking utilities.

Note: The package is under development and APIs are subject to changes.


## Installation

It's recommended to install the package using composer dependencies manager.

At the root of your application, create a file composer.json:

```json
{
  // Other requirements
  "require": {
    // Existing libraries
    // Network utilities library
    "drewlabs/net": "^0.1.0"
  },
   
  // Specify the github package repository
  repositories: [
    {
      "type": "vcs",
      "url": "git@github.com:liksoft/drewlabs-net.git"
    }
  ]
}
```

Then execute composer cli to update packages:

> composer update

## Usage

## Ping request

Ping clients provide developper with a unified interface for querying server existence using ping cli, php socket api or the generic PHP fsocketopen utility function.
When using OS Ping binary to perform query, you are required to sanitize the data send through the comminication channel as these clients are often insecure for sensitve data.
Prefer use of the Ping client as it's often fast that the php implementations. Default: Binary


```php
// Import required classes & functions
use Drewlabs\Net\Client;
use Drewlabs\Net\Method;

// Create a PING client
$client = new Drewlabs\Net\Pring(<HOST>, [<PORT>, <TMIEOUT>]);
// example
$client = new Drewlabs\Net\Pring('https://liksoft.tg');

// Send request using default Channel
$response = $client->request(); // Returns \Drewlabs\Net\PingResult class
// Send a Ping request using PHP fsockopen util
$response = $client->request(Method::FSOCKOPEN);


// Get response details
$response->latency(); // Returns the latency of the PING request
$response->ip(); // Returns the IP address of the client
```

## Extras
