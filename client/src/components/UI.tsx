import styles from './Ui.module.scss';
import React from 'react';
import { cx } from "../ui";

export function Heading({ h = 1, center = true, children }: { h?: number; center?: boolean; children: any }) {
  const compName = `h${h}`;

  return React.createElement(compName, { className: cx(styles.Heading, center && styles.center), children });
}
