import React, { useState } from 'react';
import styles from './App.module.scss';
import { setUseAws } from '../data';
import InstanceStatus from './InstanceStatus';
import SearchView from '../views/SearchView';
import ChartView from '../views/ChartView';

const DEFAULT_ROUTE = '/chart';

export default function App() {
  const [route, setRoute] = useState<string>(DEFAULT_ROUTE);

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
          <InstanceStatus onChange={v => setUseAws(v)} />
        </div>
      </header>

      {route === '/search' && <SearchView />}
      {route === '/chart' && <ChartView />}
    </div>
  );
}
