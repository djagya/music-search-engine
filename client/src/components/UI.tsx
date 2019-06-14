import styles from './Ui.module.scss';
import React from 'react';

export function Heading({ h = 1, children }: { h?: number; children: any }) {
  const compName = `h${h}`;

  return React.createElement(compName, { className: styles.Heading, children });
}
