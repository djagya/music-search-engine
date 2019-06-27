import styles from '../Chart.module.scss';
import React from 'react';
import { FIELDS, LABELS } from '../ChartPage';
import { Th } from './Grid';
import { AppleLink } from "../../../components/UI";

/**
 * "releases" grid type representation.
 */
export default function ReleasesTable({
  rows,
  charted,
  currentSort,
  onSortChange,
}: {
  rows: any[];
  charted: boolean;
  currentSort: string | null;
  onSortChange: any;
}) {
  return (
    <table className={styles.table}>
      <thead>
      <tr>
        {charted && <th>Rank</th>}
        <Th
          name="count"
          label="Count"
          sortable
          filter={false}
          currentSort={currentSort}
          onSortChange={onSortChange}
        />

        <Th
          name={FIELDS.release}
          label={LABELS[FIELDS.release]}
          sortable
          currentSort={currentSort}
          onSortChange={onSortChange}
        />
        <Th name={FIELDS.artist} label={LABELS[FIELDS.artist]} />
        <Th name={'release_genre'} label="Genre" />
        <Th name={'label_name'} label="Label" />
        <Th name={'release_year_released'} label="Released" placeholder={'[year] or [from]-[to]'} />
      </tr>
      </thead>

      <tbody>
      {rows.map(row => (
        <tr key={row._id}>
          {charted && <td>{row.rank}</td>}
          <td>{row.count}</td>

          <td>
            {row.collection_id ? (
              <AppleLink cId={row.collection_id}>{row.release_title}</AppleLink>
            ) : (
              row.release_title
            )}
          </td>
          <td>{row.artist_name.join(', ')}</td>
          <td>{row.release_genre.join(', ')}</td>
          <td>{row.label_name.join(', ')}</td>
          <td>{row.release_year_released.join(', ')}</td>
        </tr>
      ))}
      </tbody>
    </table>
  );
}
