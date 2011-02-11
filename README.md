What is Sistrence?
==================

Sistrence is a database abstraction library for PHP5.3+. I've been using it in
dozens of projects under the name "jmDB" for about six years now. It's fairly
stable and it's got a few neat features, so I'm releasing it as open-source at
this time.

There isn't much documentation for it yet, but I hope to add more in the future.


How to use it?
==============

You can either use it on the database layer:

    // Create new operation
    $op = Sis::op('mytable');

    // Set a condition id=15
    $op->eq('id', 15);

    // Fetch one row
    $row = $op->doGetOne();

    // Print something from the data
    echo $row['title'];

Or you can define database objects...

    class Customer extends SisObject
    {
   		const TABLE = 'tbl_customer';
    }

... and use them:

    $customer = new Customer(15);
    echo $customer['name'];

And that's it, really. :)


How do I set it up?
===================

Put the sistrence folder somewhere in your include path. Then do:

    require_once 'sistrence/inc.php';
    Sis::connect_mysqli('database_user', 'password', 'localhost', 'database_name');


The Sistrence cookbook
======================

Create an object

    $pet = Pet::create(array(
         'age' => 4,
         'name' => 'Sir Donathan',
         'type' => 'lion'
    ));

Use custom conditions to retrieve your objects

    $op = OlympicMedalist::op();
    $op->gt('gold_medals', 6);
    $op->eq('silver_medals', 0);
    $result = OlympicMedalist::objectify($op->doGet());


Multiple field comparisons in one command

    $knownData = array('type' => 'middle_management', 'job_desc' => '');
    $op = Employee::op();
    $op->eq($knownData);
    $op->gt('salary', '80000');
    $downsizingCandidates = Employee::objectify($knownData);


