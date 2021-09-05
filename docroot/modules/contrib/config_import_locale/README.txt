Locale: Config import


This module provides a solution to the fact that by default, interface translations are overwritten by config imports if the imported config contains a translation (or misses a translation) of a given string in translate interface.

The following changes to this behaviour are supplied by this module:

Default: Interface translations may be overwritten if the string is imported via config import
No overwrites: Existing interface translations will be kept, new translations may be added
Nothing: Config imports will never add / change any interface translations
