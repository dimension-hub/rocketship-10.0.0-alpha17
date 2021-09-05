## Dropsolid Rocketship Distribution

_curated by Dropsolid_

The intent of Dropsolid Rocketship is to make **digital business easy** by 
providing a framework and best practice examples based on real life situations 
from a company that has built and managed over 500 Drupal sites.

Dropsolid Rocketship is a distribution that enables small to mid-market 
business to profit from an optimal start when getting your feet wet in Drupal. 
It provides best practices from deployment, configuration and ways to extend 
it, proven by many cases that have implemented and resulted in this learning.

All Drupal 8 sites at Dropsolid have Rocketship at its core. Junior developers 
learn the basics with Rocketship, while senior developers have had their say 
in how an optimal development codebase looks like.

As it is our goal to make digital business easy, there is no better way than 
being transparent in how we achieve that. 

Note that the current READMEs are straight from the old private version of this
distribution, and were written with our own devs in mind. So some things may
not make sense. Feel free to create issues on 
[drupal.org](https://drupal.org/project/dropsolid_rocketship) when you find 
something that should be made clearer.


### Best installed with [Composer](https://getcomposer.org/download/):

To install the most recent beta release:
```
composer create-project dropsolid/rocketship:^10@beta PROJECTNAME --no-dev --no-interaction
```

To install the dev version:
```
composer create-project dropsolid/rocketship:10.0.x-dev PROJECTNAME --stability dev --no-interaction
```

------------------

- [Rocketship IP](#rocketship-ip)  
    - [Basics](#basics)  
    - [Features](#features)  
    - [Search API](#search-api)  
- [Before you start](#before-you-start)  
- [Installing a site](#installing-a-site)  
- [After the installation](#after-the-installation)  
- [Development & Site-building](#development--site-building)  
- [Theming](#theming)  
- [Deployment](#deployment)  
- [Infra Roadmap](#infra-roadmap)  

### Rocketship IP

##### Basics
This is the company-wide install profile for Dropsolid. It is based
around Layout Builder and the Page content type. With just those two you should 
be able to build a large array of various pages.

##### Search API
The Page content type includes a premade setup for Search API. It will index the full view mode
so as to index everything set up with Layout Builder. If you create other content types, add the 
appropriate view mode to the index as well as any other fields that may be useful to index.

When creating a View, eg. an overview of a content type, use this index if at all possible. Having every
view fed by the same Search index makes life easier, and makes setting up Facets, the preferred way of filtering
views, a breeze.

And if you must implement some strange filter or even sort, think
"Can't I just make this a Facet instead?" and then do that so others can reuse it later and
so that you can mix and match with the normal Facets.

### Before you start

- [Before you start](readme/before-install) [markdown]

### Installing a site

- [How to install](readme/install) [markdown]

### After the installation

- [After the installation](readme/after-install) [markdown]

### Demo Content

- [Demo Content](readme/democontent) [markdown]

### Development & Site-building

- [Development](readme/development) [markdown]

### Theming

- [Development](readme/theming) [markdown]

### Deployment

- [Deployment](readme/deploying) [markdown]

### Optimization

- [Optimization](readme/optimization) [markdown]
