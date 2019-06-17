import React, { useState } from 'react';
import styles from './App.module.scss';
import InstanceStatus from './InstanceStatus';
import SearchView from '../pages/SearchPage';
import ChartView from '../pages/ChartPage/ChartPage';

const DEFAULT_ROUTE = '/chart';
// const DEFAULT_ROUTE = '/search';

export default function App() {
  const [route, setRoute] = useState<string>(DEFAULT_ROUTE);
  const [awsRunning, setAwsRunning] = useState<boolean>(false);

  function handleNavClick(e: React.MouseEvent<HTMLAnchorElement>) {
    e.preventDefault();
    setRoute(e.currentTarget.pathname);
  }

  return (
    <div className={styles.App}>
      <header className={styles.header}>
        <ul className={styles.nav}>
          <a href="/search" className={route === '/search' ? styles.navActive : undefined} onClick={handleNavClick}>
            Search
          </a>
          <a href="/chart" className={route === '/chart' ? styles.navActive : undefined} onClick={handleNavClick}>
            Chart
          </a>
        </ul>

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
