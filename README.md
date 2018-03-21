# Packer-PHP

Simple Key-Value Storage for PHP.

Packer aims to be a zero-config, zero-install and works as PHP works library that developers can quickly pull into their project for use immediately for small and medium scaling usage.

[![Build Status](https://secure.travis-ci.org/thephpdeveloper/Packer-PHP.png)](http://travis-ci.org/thephpdeveloper/Packer-PHP)

## Installation *via Composer*

To use Packer in your project, add a dependency to `mauris/packer` in your project's `composer.json` file. The following is a minimal sample configuration to use  Packer in your project.

    {
        "require": {
            "mauris/packer": "1.0"
        }
    }

After which run the command:

    php composer.phar install

Learn more about [Composer](http://getcomposer.org/doc/00-intro.md).

## Usage

Once you have installed Packer as your project's dependencies using Composer, you can use the `Packer` class directly in your code.

To work with a Packer file, you create an instance of `Packer` like this:

    $packer = new Packer\Packer('config.pack');

### Writing / Overwriting

To write a key and value entry to the Packer file, simply use the `write($key, $value)` method like this:

    $packer->write('autorun', false);

### Reading

To fetch a value from a Packer file, use the `read($key)` method.

    $autorun = $packer->read('autorun');

### Deleting

To delete a value from the Packer file:

    $packer->delete('autorun');
    // $packer->exist('autorun') === false

### Fetch all keys

To iterate through the Packer file, you can fetch the keys using the `keys()` method:

    echo '<ul>';
    foreach($packer->keys() as $key){
        echo '<li>' . $packer->read($key) . '</li>';
    }
    echo '</ul>';

### Clearing

    To remove all entries from the Packer file:

    $packer->clear();

## License

Packer is released open source under the New BSD 3-Clause License.
