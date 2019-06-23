import React, { useEffect, useState } from 'react';
import { PAGE_SIZE } from '../ChartPage';
import { ChartResponse } from '../../../types';
import styles from './Grid.module.scss';
import { formatTotal } from '../../../utils';

const SORT_ASC = 1;
const SORT_DESC = -1;

interface GridProps {
  response: ChartResponse | null;
  onPageChange: any;
  children: any;
}

export default function Grid({ response, onPageChange, children }: GridProps) {
  return (
    <div className={styles.Grid}>
      <Summary response={response} />

      <div className={styles.tableContainer}>{children}</div>

      {response && <Pagination response={response} onPageChange={onPageChange} />}
    </div>
  );
}

function Summary({ response }: { response: ChartResponse | null }) {
  const range = ({ page, pageSize }: ChartResponse['pagination']) =>
    `${page * pageSize + 1} - ${Math.min(response!.total.value, (page + 1) * pageSize)}`;
  return (
    <div className={styles.Summary}>
      <span>
        <b>Displaying:</b> {response ? range(response.pagination) : '0'}
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

// todo: implement sorting
export function Th({
  name,
  label,
  placeholder,
  currentSort,
  sortable = false,
  rangeFilter = false,
  onSortChange,
}: {
  name: string;
  label: string;
  placeholder?: string;
  currentSort?: string | null; // [-]field
  sortable?: boolean;
  rangeFilter?: boolean;
  onSortChange?: any;
}) {
  const direction =
    sortable && currentSort && currentSort.indexOf(name) !== -1
      ? currentSort.indexOf('-') === 0
      ? SORT_DESC
      : SORT_ASC
      : null;
  const icon = direction
    ? {
      [SORT_ASC]: '▲',
      [SORT_DESC]: '▼',
    }[direction]
    : '';

  return (
    <th>
      <div>
        <span>
          {sortable ? (
            <a
              href="#"
              onClick={e => {
                e.preventDefault();
                onSortChange(direction === 1 ? `-${name}` : name);
              }}
            >
              {label} {icon}
            </a>
          ) : (
            label
          )}
        </span>
        {rangeFilter ? (
          <>
            <input type="text" name={`query[${name}][from]`} placeholder={`From ${placeholder}` || `${label} filter`} />
            <input type="text" name={`query[${name}][to]`} placeholder={`To ${placeholder}` || `${label} filter`} />
          </>
        ) : (
          <input type="text" name={`query[${name}]`} placeholder={placeholder || `${label} filter`} />
        )}
      </div>
    </th>
  );
}

function Pagination({ response, onPageChange }: { response: ChartResponse; onPageChange: any }) {
  const [prevStack, setPrevStack] = useState<string[]>([]);
  const VISIBLE_PAGES_LIMIT = 10;
  const pagesCount = Math.ceil(response.total.value / PAGE_SIZE);
  const displayPages = Math.min(pagesCount, VISIBLE_PAGES_LIMIT);
  const { page, after } = response.pagination;

  // Reset stack of prev cursors.
  useEffect(() => {
    if (page === 0) {
      setPrevStack([]);
    }
  }, [page]);

  if (after) {
    // todo: fix, when first item is the second after, so when go back to zero page it doesn't fully reset
    return (
      <ul className={styles.Pagination}>
        {page != 0 && (
          <li>
            <a
              href="#"
              onClick={() => {
                const prev = prevStack.pop();
                setPrevStack(prevStack);
                onPageChange(page - 1, prev);
              }}
            >
              ◀
            </a>
          </li>
        )}
        <li className={styles.active}>
          <a>{page + 1}</a>
        </li>
        {page + 1 < pagesCount && (
          <li>
            <a
              href="#"
              onClick={() => {
                prevStack.push(after);
                setPrevStack(prevStack);
                onPageChange(page + 1, after);
              }}
            >
              ►
            </a>
          </li>
        )}
      </ul>
    );
  }

  return (
    <ul className={styles.Pagination}>
      {Array.from(Array(displayPages).keys()).map(p => (
        <li className={page === p ? styles.active : undefined} key={p}>
          <a href="#" onClick={() => onPageChange(p)}>
            {p + 1}
          </a>
        </li>
      ))}

      {displayPages < pagesCount && <li>...</li>}
    </ul>
  );
}
