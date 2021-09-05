# Node keep module

Adds two base fields to all nodes.

Node keeper: prevents people who don't have the 'bypass node access' permission
from deleting nodes where that checkbox is enabled.

Alias keeper: prevents people who don't have the 'bypass node access' permission
from changing the node's alias if the Alias keeper checkbox is checked. Is only
installed if the 'pathauto' module exists.

Use-case: you've set up a node as an overview, and other nodes have their alias
set to be [this-node-alias]/their-own-alias. With this module you can protect
the alias you set, as well as make sure the node doesn't get deleted.

@todo: Prevent the alias config entity from being edited/deleted if it's 
attached to a node with Alias Keep checked. Currently it only prevents changes
in the node edit form, so users who can manage aliases through the overview
can still change/delete it.
