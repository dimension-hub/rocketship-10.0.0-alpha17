import React from 'react';

import contactTwig from './contact.twig';
import contactDoormatData from './contact--doormat.yml';

// documentation
import mdx from './contact.mdx';

/**
 * Storybook Definition.
 */
export default {
  title: 'Molecules/Features/Office',
  parameters: { docs: { page: mdx } }, // needed to load an mdx file for documentation: componentName.mdx
};

export const contactInDoormat = () => (
  <div dangerouslySetInnerHTML={{ __html: contactTwig(contactDoormatData) }} />
);

