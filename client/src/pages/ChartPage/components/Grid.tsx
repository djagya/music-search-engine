import React from 'react';
import { PAGE_SIZE } from '../ChartPage';
import { ChartResponse } from '../../../types';
import styles from './Grid.module.scss';
import { formatTotal } from '../../../utils';

interface GridProps {
  response: ChartResponse | null;
  onPageChange: any;
  children: any;
}

export default function Grid({ response, onPageChange, children }: GridProps) {
  return (
    <div className={styles.Grid}>
      <Summary response={response} />

      <div className={styles.tableContainer}>
        {children}
      </div>

      {response && <Pagination response={response} onPageChange={onPageChange} />}
    </div>
  );
}

function Summary({ response }: { response: ChartResponse | null }) {
  const range = ({ page, pageSize }: ChartResponse) => `${page * pageSize + 1} - ${(page + 1) * pageSize}`;
  return (
    <div className={styles.Summary}>
      <span>
        <b>Displaying:</b> {response ? range(response) : '0'}
      </span>
      <span>
        <b>Total:</b> {response ? formatTotal(response.total) : '0'}
      </span>
      <span>
        <b>Took:</b> {response ? `${response.took}` : '0'}ms
      </span>
    </div>
  );
}

export function Th({ name, label, placeholder }: { name: string; label: string; placeholder?: string }) {
  return (
    <th>
      <div>
        <span>{label}</span>
        <input type="text" name={`query[${name}]`} placeholder={placeholder || `${label} filter`} />
      </div>
    </th>
  );
}

function Pagination({ response, onPageChange }: { response: ChartResponse; onPageChange: any }) {
  const VISIBLE_PAGES_LIMIT = 10;
  const pagesCount = Math.ceil(response.total.value / PAGE_SIZE);
  const displayPages = Math.min(pagesCount, VISIBLE_PAGES_LIMIT);

  return (
    <ul className={styles.Pagination}>
      {Array.from(Array(displayPages).keys()).map(p => (
        <li className={response.page === p ? styles.active : undefined} key={p}>
          <a href="#" onClick={() => onPageChange(p)}>
            {p + 1}
          </a>
        </li>
      ))}

      {displayPages < pagesCount && <li>...</li>}
    </ul>
  );
}
