import React, { useState } from 'react';
import styles from './App.module.scss';
import InstanceStatus from './InstanceStatus';
import SearchView from '../pages/SearchPage/SearchPage';
import ChartView from '../pages/ChartPage/ChartPage';
import { Heading } from './UI';

// const DEFAULT_ROUTE = '/chart';
const DEFAULT_ROUTE = '/search';

const TITLES: { [route: string]: string } = {
  '/chart': 'Chart',
  '/search': 'Search',
};

export default function App() {
  const [route, setRoute] = useState<string>(DEFAULT_ROUTE);
  const [awsRunning, setAwsRunning] = useState<boolean>(false);

  function handleNavClick(e: React.MouseEvent<HTMLAnchorElement>) {
    e.preventDefault();
    setRoute(e.currentTarget.pathname);
  }

  const link = (r: string) => (
    <a href={r} className={route === r ? styles.navActive : undefined} onClick={handleNavClick}>
      {TITLES[r]}
    </a>
  );

  return (
    <div className={styles.App}>
      <header className={styles.header}>
        <ul className={styles.nav}>
          {link('/search')}
          {link('/chart')}
        </ul>

        <Heading h={2}>{TITLES[route]}</Heading>

        <div className={styles.instance}>
          <span>AWS instance &nbsp;</span>
          <InstanceStatus onChange={v => setAwsRunning(v)} />
        </div>
      </header>

      {route === '/search' && <SearchView />}
      {route === '/chart' && <ChartView />}
    </div>
  );
}
