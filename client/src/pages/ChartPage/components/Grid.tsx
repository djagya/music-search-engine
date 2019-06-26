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

      {response &&
      (response.pagination.after ? (
        <CursorPagination response={response} onPageChange={onPageChange} />
      ) : (
        <Pagination response={response} onPageChange={onPageChange} />
      ))}
    </div>
  );
}

function Summary({ response }: { response: ChartResponse | null }) {
  const range = ({ page, pageSize }: ChartResponse['pagination']) =>
    `${page * pageSize + 1} - ${Math.min(response!.total.value, (page + 1) * pageSize)}`;
  return (
    <div className={styles.Summary}>
      <span>
        <b>Displaying:</b> {response ? `page ${response.pagination.page + 1} (${range(response.pagination)})` : '0'}
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
  filter = true,
  rangeFilter = false,
  onSortChange,
}: {
  name: string;
  label: string;
  placeholder?: string;
  currentSort?: string | null; // [-]field
  sortable?: boolean;
  filter?: boolean;
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

  console.log(direction);

  return (
    <th>
      <div>
        <span>
          {sortable ? (
            <a
              href="#"
              onClick={e => {
                e.preventDefault();
                onSortChange(direction === SORT_DESC ? name : `-${name}`);
              }}
            >
              {label}&nbsp;{icon}
            </a>
          ) : (
            label
          )}
        </span>
        {filter && rangeFilter && (
          <>
            <input type="text" name={`query[${name}][from]`} placeholder={`From ${placeholder}` || `${label} filter`} />
            <input type="text" name={`query[${name}][to]`} placeholder={`To ${placeholder}` || `${label} filter`} />
          </>
        )}
        {filter && !rangeFilter && (
          <input type="text" name={`query[${name}]`} placeholder={placeholder || `${label} filter`} />
        )}
      </div>
    </th>
  );
}


// todo: one additional page is disaplyed
function Pagination({ response, onPageChange }: { response: ChartResponse; onPageChange: any }) {
  const PAGES_LIMIT = 5;
  const pagesCount = Math.ceil(response.total.value / PAGE_SIZE);
  const { page } = response.pagination;

  const fromPage = Math.max(page - PAGES_LIMIT, 0);
  // Increase the number of displayed pages on the right when not all on the left are displayed.
  const toPage = Math.min(page + PAGES_LIMIT + Math.max(PAGES_LIMIT - fromPage - 1, 0), pagesCount);
  const pages = [];
  for (let i = fromPage; i <= toPage; i++) {
    pages.push(i);
  }

  return (
    <ul className={styles.Pagination}>
      {fromPage > 0 && <li>...</li>}
      {pages.map(p => (
        <li className={page === p ? styles.active : undefined} key={p}>
          {page !== p ? (
            <a href="#" onClick={() => onPageChange(p)}>
              {p + 1}
            </a>
          ) : (
            <span>{p + 1}</span>
          )}
        </li>
      ))}
      {toPage < pagesCount && <li>...</li>}
    </ul>
  );
}

function CursorPagination({ response, onPageChange }: { response: ChartResponse; onPageChange: any }) {
  const [prevStack, setPrevStack] = useState<string[]>([]);
  const pagesCount = Math.ceil(response.total.value / PAGE_SIZE);
  const { page, after, prev } = response.pagination;

  // Reset stack of prev cursors.
  useEffect(() => {
    if (page === 0) {
      setPrevStack([]);
    }
  }, [page]);

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
        <span>{page + 1}</span>
      </li>
      {page + 1 < pagesCount && (
        <li>
          <a
            href="#"
            onClick={() => {
              if (prev) {
                prevStack.push(prev);
              }
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
