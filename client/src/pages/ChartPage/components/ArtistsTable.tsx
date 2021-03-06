import styles from '../Chart.module.scss';
import React from 'react';
import { FIELDS, LABELS } from '../ChartPage';
import { Th } from './Grid';
import { AppleLink } from "../../../components/UI";

/**
 * "artists" grid type representation.
 */
export default function ArtistsTable({
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
          name={FIELDS.artist}
          label={LABELS[FIELDS.artist]}
          sortable
          currentSort={currentSort}
          onSortChange={onSortChange}
        />
        <Th name={'release_genre'} label="Genre" />
        <Th name={'label_name'} label="Label" />
      </tr>
      </thead>

      <tbody>
      {rows.map(row => (
        <tr key={row._id}>
          {charted && <td>{row.rank}</td>}

          <td>{row.count}</td>
          <td>{row.artist_id ? <AppleLink aId={row.artist_id}>{row.artist_name}</AppleLink> : row.artist_name}</td>
          <td>{row.release_genre.join(', ')}</td>
          <td>{row.label_name.join(', ')}</td>
        </tr>
      ))}
      </tbody>
    </table>
  );
}
