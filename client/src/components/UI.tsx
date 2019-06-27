import styles from './Ui.module.scss';
import React from 'react';
import { cx } from "../ui";

export function Heading({ h = 1, center = true, children }: { h?: number; center?: boolean; children: any }) {
  const compName = `h${h}`;

  return React.createElement(compName, { className: cx(styles.Heading, center && styles.center), children });
}

export function AppleLink({ cId, aId, children }: { cId?: number; aId?: number; children: any }) {
  const type = cId ? 'album' : 'artist';
  const id = cId || aId;
  return (
    <a href={`https://music.apple.com/us/${type}/${id}`} target="_blank">
      {children}
    </a>
  );
}
